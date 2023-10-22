<?php 


class Escalonador {

    const PRONTO = 5;
    const EXECUTANDO = 6;
    const BLOQUEADO = 7;

    public $quantum;
    private $BCP = [];
    
    private $pc = 0;
    private $x = 0;
    private $y = 0;

    private $trocas = 0;
    private $instrucoes = 0;

    public $pronto = [];
    public $bloqueado = [];
    public Processo $executando;

    public function __construct()
    {
        $this->quantum = intval(file_get_contents("programas/quantum.txt"));
    }

    public function carrega_processos()
    {
        // Carregando os arquivos/Processos para a fila de prontos
        for ($id=1; $id <= 3; $id++) {
            $proc = new Processo($id);
            $this->log("Carregando " . $proc->nome);
            
            //Poe na fila de pronto
            array_push($this->pronto, $proc);

            //Inicializa o BCP
            $this->BCP[$proc->id]['estado'] = self::PRONTO;
            $this->BCP[$proc->id]['x'] = 0;
            $this->BCP[$proc->id]['y'] = 0;
            $this->BCP[$proc->id]['pc'] = 0;
        }
    }
    
    public function executar()
    {
        
        while(!empty($this->pronto) || !empty($this->bloqueado)){
                
            $this->decrementaBloqueados();
            
            if(!empty($this->pronto)) {
                $this->executando = array_shift($this->pronto);
                
                $this->log(PHP_EOL . "Executando " . $this->executando->nome);
                $this->carrega_contexto($this->executando);

                $prox = true;

                for ($q=0; $q < $this->quantum && $prox ; ++$q) {
                    $prox = $this->leia($this->executando);
                }
                
                $this->salva_contexto($this->executando);

                if (!empty($this->BCP[$this->executando->id])) {
                    $this->log("Interrompendo ". $this->executando->nome ." após $q instruções");
                }


                // Só volta pra fila de pronto se não estiver bloqueado e ainda estiver no BCP
                if (empty($this->bloqueado[$this->executando->id]) && !empty($this->BCP[$this->executando->id])) {
                    array_push($this->pronto, $this->executando);
                }
                
                $this->trocas++;
            }
        }
        
        $this->log("MEDIA DE TROCAS: " . $this->trocas / 10);
        $this->log("MEDIA DE INSTRUCOES: " . $this->instrucoes / $this->trocas);
        $this->log("QUANTUM: " . $this->quantum);
    }

    public function leia(Processo $p)
    {
        $instr = $p->instrucoes[$this->pc];

        $this->pc++;
        $this->instrucoes++;

        //Verifica qual o tipo de instrução a partir do primeiro caracter
        // Se for X ou Y é instrução de armazenamento
        // E -> Entrada/Saída
        // C -> Roda o comando fictício
        // S -> Fim do programa
        switch ($instr[0]) {
            case 'X':
                
                $value = explode('=', $instr)[1];
                $this->x = $value;
                return true;

                break;
            case 'Y':
                
                $value = explode('=', $instr)[1];
                $this->y = $value;
                return true;

                break;
            case 'E':

                $this->log("E/S iniciada em " . $this->executando->nome);
                //bloqueado por dois quanta
                $this->executando->espera = 2;
                // BLOQUEIA
                $this->bloqueado[$this->executando->id] = $this->executando;
                return false;

                break;
            case 'C':
                
                // Executando blah blah blah
                return true;

                break;
            case 'S':
                
                $this->log($this->executando->nome . " terminado. X=$this->x. Y=$this->y");
                //Remove o programa do BCP
                $this->BCP[$this->executando->id] = null;
                return false;

                break;
            default:
                
                die("DEU RUIM<<INSTRUCAO INCORRETA");

                break;
        }
    }

    //Decrementa o tempo de espera de todos os bloqueados
    public function decrementaBloqueados(){
        foreach($this->bloqueado as $id => $p) {

            // Libera o bloqueado para a fila de pronto caso espera seja zero
            if ($this->bloqueado[$id]->espera == 0) {
                $this->BCP[$p->id]['estado'] = self::PRONTO;

                
                $desbloqueado = $this->bloqueado[$p->id];

                // Tirou da fila de bloqueado
                unset($this->bloqueado[$p->id]);

                //Processo Desbloqueado é adicionado a fila de pronto
                array_push($this->pronto, $desbloqueado);

                continue;
            }

            //Decrementa espera de todos os bloqueados 
            $this->bloqueado[$id]->espera--;
        }
    }

    
    public function salva_contexto(Processo $p)
    {
        if (!empty($this->BCP[$p->id])) {
            $this->BCP[$p->id]['x'] = $this->x;
            $this->BCP[$p->id]['y'] = $this->y;
            $this->BCP[$p->id]['pc'] = $this->pc;
        }
    }

    public function carrega_contexto(Processo $p)
    {
        if (empty($this->BCP[$p->id])){
            die('TENTANDO CARREGAR CONTEXTO DE PROCESSO TERMINADO');
        }

        $this->x = $this->BCP[$p->id]['x'];
        $this->y = $this->BCP[$p->id]['y'];
        $this->pc = $this->BCP[$p->id]['pc'];
    }

    public function log($linha)
    {
        file_put_contents("logs/log" . ($this->quantum < 10 ? '0' : '') . $this->quantum . '.txt', $linha . PHP_EOL, FILE_APPEND);
    }


}

class Processo {

    public $id;
    public $nome;
    public $instrucoes = [];

    public $espera = 0;
    
    function __construct($id)
    {
        $this->id = $id;

        //Carrega comandos
        $arq = file_get_contents("programas/". ($id < 10 ? '0' : '') ."$id.txt");

        //Exclui valores nulos
        $arq = array_filter(explode("\n", $arq));
        
        //Retira o nome
        $this->nome = array_shift($arq);

        //Guarda instrucoes no objeto
        $this->instrucoes = $arq;

    }
}


$esc = new Escalonador();

$esc->carrega_processos();

$esc->executar();