<?php
/**
 * Created by PhpStorm.
 * User: claudio.santos
 * Date: 20/03/2019
 * Time: 14:54
 */

namespace RSC\model;

use CWG\PagSeguro\PagSeguroAssinaturas;
use CWG\PagSeguro\PagSeguroCompras;
use MocaBonita\tools\eloquent\MbDatabase;
use RSC\common\Sessao;
use RSC\model\Contrato;

class Assinatura
{
    private $email = "claudiopablosilva@hotmail.com";
    private $token = "2382B416442D464CADC943D232AD6045";
    private $sandbox = true;
    private $pagseguro = null;

    function __construct()
    {
        $this->pagseguro = new PagSeguroAssinaturas($this->email, $this->token, $this->sandbox);
    }

    public function iniciaSessao()
    {
        $id = $this->pagseguro->iniciaSessao();
        return [$id];
    }

    public function criarPlano()
    {
        //Cria um nome para o plano
        $this->pagseguro->setReferencia('LUCRO PRESUMIDO - SERVIÇO');

//Cria uma descrição para o plano
        $this->pagseguro->setDescricao('Libera o acesso mensalmente aos serviços de abertura de empresa lucro presumido de serviço com 1 sócio e nenhum funcionário da RSC Contabilidade');

//Valor a ser cobrado a cada renovação
        $this->pagseguro->setValor('180.00');

//De quanto em quanto tempo será realizado uma nova cobrança (MENSAL, BIMESTRAL, TRIMESTRAL, SEMESTRAL, ANUAL)
        $this->pagseguro->setPeriodicidade(PagSeguroAssinaturas::MENSAL);

//=== Campos Opcionais ===//
//Após quanto tempo a assinatura irá expirar após a contratação = valor inteiro + (DAYS||MONTHS||YEARS). Exemplo, após 5 anos
        $this->pagseguro->setExpiracao(25, 'YEARS');

//URL para redicionar a pessoa do portal PagSeguro para uma página de cancelamento no portal
        //$this->pagseguro->setURLCancelamento('http://localhost/rsc/wp-admin/cancelando.php');

//Local para o comprador será redicionado após a compra com o código (code) identificador da assinatura
        //$this->pagseguro->setRedirectURL('http://carloswgama.com.br/pagseguro/not/assinando.php');

//Máximo de pessoas que podem usar esse plano. Exemplo 10.000 pessoas podem usar esse plano
        $this->pagseguro->setMaximoUsuariosNoPlano(10000);

        try {
            $codigoPlano = $this->pagseguro->criarPlano();
            return ['message' => 'Plano com código ' . $codigoPlano . ' criado com sucesso'];
        } catch (\Exception $e) {
            Log::createFromException($e);
            throw new \Exception('Não foi possível criar o plano.' . $e->getMessage());
        }

    }

    public function assinar($dados)
    {
        $data = implode("/", array_reverse(explode("-", $dados['data_nascimento'])));
        //Nome do comprador igual a como esta no CARTÂO
        $this->pagseguro->setNomeCliente($dados['nome']);
        //Email do comprovador
        $this->pagseguro->setEmailCliente($dados['email']);
        //Informa o telefone DD e número
        $this->pagseguro->setTelefone($dados['ddd'], $dados['telefone']);
        //Informa o CPF
        $this->pagseguro->setCPF($dados['cpf']);
        //Informa o endereço RUA, NUMERO, COMPLEMENTO, BAIRRO, CIDADE, ESTADO, CEP
        $this->pagseguro->setEnderecoCliente($dados['rua'], $dados['numero'], $dados['complemento'], $dados['bairro'], $dados['cidade'], $dados['estado'], $dados['cep']);
        //Informa o ano de nascimento
        $this->pagseguro->setNascimentoCliente($data);
        //Infora o Hash  gerado na etapa anterior (assinando.php), é obrigatório para comunicação com checkoutr transparente
        $this->pagseguro->setHashCliente($dados['hash']);
        //Informa o Token do Cartão de Crédito gerado na etapa anterior (assinando.php)
        $this->pagseguro->setTokenCartao($dados['token']);
        //Código usado pelo vendedor para identificar qual é a compra
        $this->pagseguro->setReferencia($dados['id_cliente']);
        //Plano usado (Esse código é criado durante a criação do plano)
        $this->pagseguro->setPlanoCode($dados['codigo_pagseguro']);

        try {
            $codigo = $this->pagseguro->assinaPlano();

            Contrato::where('id_cliente', $dados['id_cliente'])
                ->update(['codigo_assinatura' => $codigo]);

            $pagamento = (new Pagamento())->inserir([
                'id_contrato' => $dados['id_contrato'],
                'valor' => $dados['mensalidade'],
                'id_status' => 1,
            ]);

            Cliente::where('id', $dados['id_cliente'])
                ->update(['completou' => true]);

            return ['message' => 'O seu pagamento está sendo processado...'];

        } catch (\Exception $e) {
            Log::createFromException($e);
            throw new \Exception('Não foi possível realizar sua assinatura' . $e->getMessage());
        }
    }

    public function cancelar($codePagSeguro)
    {
        try {
            return $this->pagseguro->cancelarAssinatura($codePagSeguro);
        } catch (\Exception $e) {
            Log::createFromException($e);
            throw new \Exception('Não foi possível cancelar sua assinatura.');
        }
    }

    public function getNotificacao($post)
    {
        header("access-control-allow-origin: https://sandbox.pagseguro.uol.com.br");
        try {
            $compra = new PagSeguroCompras($this->email, $this->token, $this->sandbox);
            if ($post['notificationType'] == 'transaction') {
                $codigo = $post['notificationCode']; //Recebe o código da notificação e busca as informações de como está a assinatura
                $response = $compra->consultarNotificacao($codigo);

                $idContrato = Contrato::where('id_cliente', '=', $response['reference'])
                    ->first(['id']);

                (new Pagamento())->inserir([
                    'id_contrato' => $idContrato->id,
                    'id_status' => $response['status'],
                    'data_transacao' => $response['lastEventDate'],
                    'codigo_transacao' => $response['code'],
                    'id_forma_pagamento' => 1,
                    'valor' => $response['grossAmount'],
                ]);

                return ['message' => 'Pagamento processado com sucesso'];

            } elseif ($post['notificationType'] == 'preApproval') {
                $codigo = $post['notificationCode']; //Recebe o código da notificação e busca as informações de como está a assinatura
                $response = $this->pagseguro->consultarNotificacao($codigo);
                Contrato::where('codigo_assinatura', $response['code'])
                    ->update(['id_status_assinatura' => $this->setStatusAssinatura($response['status'])]);

                return ['message' => 'Assinatura atualizada com sucesso'];
            }
            return;
        } catch (\Exception $e) {
            Log::createFromException($e);
            throw new \Exception("Não foi possível atualizar o pagamento");
        }
    }

    public function getDadosAssinatura()
    {
        $idCliente = Sessao::instanciar()->get('user')[0]['id'];
        $dados = Contrato::select(
            "cli.nome as nome",
            "men.socios_minimo",
            "men.socios_maximo",
            "men.funcionarios_minimo",
            "men.funcionarios_maximo",
            "tpe.nome as tipo_empresa",
            "men.mensalidade as valor",
            "fat.nome as faturamento",
            "sta.nome as status"
        )
            ->from("rsc_contrato as con")
            ->join("rsc_cliente as cli", "cli.id", "=", "con.id_cliente")
            ->join("rsc_mensalidade as men", "men.id", "=", "con.id_mensalidade")
            ->join("rsc_tipo_empresa as tpe", "tpe.id", "=", "men.id_tipo_empresa")
            ->join("rsc_faturamento as fat", "fat.id", "=", "men.id_faturamento")
            ->join("rsc_status_assinatura as sta","sta.id","=","con.id_status_assinatura")
            ->where("cli.id", "=", $idCliente)
            ->get()
            ->toArray();

        if (!is_array($dados) || empty($dados))
            throw new \Exception('Não foi possível abrir sua assinatura!');

        return $dados;
    }

    private function setStatusAssinatura($status)
    {
        $newStatus = null;
        switch ($status) {
            case 'INITIATED':
                $newStatus = 1;
                break;
            case 'PENDING':
                $newStatus = 2;
                break;
            case 'ACTIVE':
                $newStatus = 3;
                break;
            case 'PAYMENT_METHOD_CHANGE':
                $newStatus = 4;
                break;
            case 'SUSPENDED':
                $newStatus = 5;
                break;
            case 'CANCELLED':
                $newStatus = 6;
                break;
            case 'CANCELLED_BY_RECEIVER':
                $newStatus = 7;
                break;
            case 'CANCELLED_BY_SENDER':
                $newStatus = 8;
                break;
            case 'EXPIRED':
                $newStatus = 9;
                break;
        }

        return $newStatus;
    }


}