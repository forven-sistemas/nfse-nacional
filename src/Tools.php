<?php

namespace Hadder\NfseNacional;

use DOMDocument;
use NFePHP\Common\Certificate;

class Tools extends RestCurl
{
    public function __construct(string $config, Certificate $cert)
    {
        parent::__construct($config, $cert);
    }

    public function consultarNfseChave($chave)
    {
        $operacao = 'nfse/' . $chave;
        $retorno = $this->getData($operacao);

        if (isset($retorno['erro'])) {
            return $retorno;
        }
        if ($retorno) {
            $base_decode = base64_decode($retorno['nfseXmlGZipB64']);
            $gz_decode = gzdecode($base_decode);
            return mb_convert_encoding($gz_decode, 'ISO-8859-1', 'UTF-8');
        }
        return null;
    }

    public function consultarDpsChave($chave)
    {
        $operacao = 'dps/' . $chave;
        $retorno = $this->getData($operacao);

        return $retorno;
    }

    public function consultarNfseEventos($chave, $tipoEvento = null, $nSequencial = null)
    {
        $operacao = 'nfse/' . $chave . '/eventos';
        if ($tipoEvento) {
            $operacao .= '/' . $tipoEvento;
        }
        if ($nSequencial) {
            $operacao .= '/' . $nSequencial;
        }
        $retorno = $this->getData($operacao);
        return $retorno;
    }

    public function consultarDanfse($chave)
    {
        $operacao = 'danfse/' . $chave;
        $retorno = $this->getData($operacao, null, 2);
        if (isset($retorno['erro'])) {
            return $retorno;
        }
        if ($retorno) {
            return $retorno;
        }
        if(empty($retorno)){
            return $this->consultarDanfseNfse($chave);
        }
        return null;
    }

    /**
     * Consulta o DANFSe via NFSe caso o serviço direto falhe
     *
     * @param string $chave
     * @return array|binary|null
     */
    public function consultarDanfseNfse($chave)
    {
        $operacao = 'Certificado';
        $retorno = $this->getData($operacao, null, 3);
        if(isset($retorno) and isset($retorno['sucesso']) and $retorno['sucesso']==true){
            $operacao = 'Notas/Download/DANFSe/'.$chave;
            $retorno = $this->getData($operacao, null, 3);
        }
        if (isset($retorno['erro'])) {
            return $retorno;
        }
        if ($retorno) {
            return $retorno;
        }
        return null;
    }

    public function enviaDps($content)
    {
        //$content = $this->canonize($content);
        $content = $this->sign($content, 'infDPS', '', 'DPS');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz = gzencode($content);
        $data = base64_encode($gz);
        $dados = [
            'dpsXmlGZipB64' => $data
        ];
        $retorno = $this->postData('nfse', json_encode($dados));
        return $retorno;
    }

    /**
     * Distribui DF-e a partir do último NSU informado (API ADN).
     *
     * Retorna até 50 documentos fiscais eletrônicos (NFS-e e Eventos)
     * vinculados ao CPF/CNPJ do certificado digital utilizado na conexão.
     * O interessado deve aguardar no mínimo 1 hora antes de nova consulta
     * quando o retorno indicar que não há mais documentos (ultNSU == maxNSU).
     *
     * @param int $ultimoNsu Último NSU recebido. Use 0 na primeira consulta.
     * @return array{ultNSU: string, maxNSU: string, loteDistDFe: array}|array
     */
    public function distribuirDfe(int $ultimoNsu = 0): array
    {
        $operacao = 'DFe/' . $ultimoNsu;
        $retorno = $this->getData($operacao, null, 2);
        return $retorno ?? [];
    }

    /**
     * Consulta um DF-e específico pelo NSU informado (API ADN).
     *
     * Permite recuperar pontualmente um documento identificado como
     * faltante na base de dados local.
     *
     * @param int $nsu Número Sequencial Único do documento
     * @return array
     */
    public function consultarDfePorNsu(int $nsu): array
    {
        $operacao = 'DFe/' . $nsu;
        $retorno = $this->getData($operacao, null, 2);
        return $retorno ?? [];
    }

    public function cancelaNfse($std)
    {
        $dps = new \Hadder\NfseNacional\Dps($std);
        $content = $dps->renderEvento($std);
        //$content = $this->canonize($content);
        $content = $this->sign($content, 'infPedReg', '', 'pedRegEvento');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . $content;
        $gz = gzencode($content);
        $data = base64_encode($gz);
        $dados = [
            'pedidoRegistroEventoXmlGZipB64' => $data
        ];
        $operacao = 'nfse/' . $std->infPedReg->chNFSe . '/eventos';
        $retorno = $this->postData($operacao, json_encode($dados));
        return $retorno;
    }

    protected function canonize($content)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($content);
        // dump($dom->saveXML());
        return $dom->C14N(false, false, null, null);
    }
}