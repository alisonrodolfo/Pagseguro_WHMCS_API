<?php

/*
 * @ Módulo PagSeguro com Retorno Automático v0.2
 */

function pagseguro_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value" => "PagSeguro"),
     "token" => array("FriendlyName" => "Token", "Type" => "text", "Size" => "30", "Description" => "pagseguro.com.br &raquo; Ferramentas &raquo; Retorno automático", ),
     "login" => array("FriendlyName" => "Login WHMCS", "Type" => "text", "Size" => "40", "Description" => "Login WHMCS", ),
     "conta" => array("FriendlyName" => "Conta", "Type" => "text", "Size" => "60", "Description" => "E-mail de sua conta no PagSeguro", ),
     "status" => array("FriendlyName" => "Status da Transação", "Type" => "text", "Size" => "60", "Description" => "%status%, em %data%, via %tipopagamento%, ID: %transid% <img src='../images/help.gif' border='0' title='Aprovado, em 00/00/0000, via Boleto, ID: 123' />", ),
     "taxa_p" => array("FriendlyName" => "Taxa %", "Type" => "text", "Size" => "4", "Description" => "Taxa em porcentagem que será adicionada a fatura e a taxa auxiliar", ),
     "taxa_a" => array("FriendlyName" => "Taxa Auxiliar", "Type" => "text", "Size" => "4", "Description" => "Valor adicional. Ex.: 0.50 (50 centavos)", ),
     "img" => array("FriendlyName" => "Imagem", "Type" => "text", "Size" => "60", "Description" => "Imagem PagSeguro", ),
     "img_link" => array("FriendlyName" => "Link da Imagem", "Type" => "text", "Size" => "60", "Description" => "Endereço do PagSeguro com o seu ID de indicação", ),
     "img_text" => array("FriendlyName" => "Texto da Imagem", "Type" => "text", "Size" => "60", "value" => "Clique aqui e conheça o PagSeguro.", "Description" => "Texto indicando o PagSeguro", ),
     "link_conf" => array("FriendlyName" => "Página de Confirmação", "Type" => "text", "Size" => "60", "value" => "http://www.seusite.com.br/confirmacao.html", "Description" => "Página de confirmação de pagamento", ),
    );
	return $configarray;
}

function pagseguro_link($params) {

	# Gateway Specific Variables
	$ps_conta			= $params['conta'];
	$ps_taxa_p			= $params['taxa_p'];
	$ps_taxa_a			= $params['taxa_a'];
	$ps_imagem			= $params['img'];
	$ps_imagem_link 	= $params['img_link'];
	$ps_imagem_text		= $params['img_text'];

	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$description = $params["description"];
    $amount = $params['amount']; # Format: ##.##
    $currency = $params['currency']; # Currency Code

	# Client Variables
	$firstname = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$address1 = $params['clientdetails']['address1'];
	$address2 = $params['clientdetails']['address2'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = str_replace("-", "", $params['clientdetails']['postcode']);
	$country = $params['clientdetails']['country'];
	$phone = str_replace(" ", "", str_replace("-", "", $params['clientdetails']['phonenumber']));

	# System Variables
	$companyname = $params['companyname'];
	$systemurl = $params['systemurl'];
	$currency = $params['currency'];

	$taxas = ($ps_taxa_p / 100) * $amount + $ps_taxa_a;
	$total = $taxas + $amount;

	# Enter your code submit to the gateway...
	$code = '<br />Valor da Fatura: <b>R$ '.$amount.'</b><br>
	Taxas de Cobrança PagSeguro: <b>R$ '.number_format($taxas, 2, '.', '').'</b><br />
	Valor a Pagar: <b>R$ '.number_format($total, 2, '.', '').'</b><br /><br />

    <a href="'.$ps_imagem_link.'" target="_blank"><img src="'.$ps_imagem.'" title="'.$ps_imagem_text.'" border="0" /></a>

<form name="pagseguro" action="https://pagseguro.uol.com.br/checkout/checkout.jhtml" target="_blank" method="post">
<input type="hidden" name="email_cobranca" value="'.$ps_conta.'" />
<input type="hidden" name="tipo" value="CP" />
<input type="hidden" name="moeda" value="BRL" />
<input type="hidden" name="ref_transacao" value="'.$invoiceid.'" />
<input type="hidden" name="item_id_1" value="'.$invoiceid.'" />
<input type="hidden" name="item_descr_1" value="'.$companyname.' - Fatura #'.$invoiceid.'" />
<input type="hidden" name="item_quant_1" value="1" />
<input type="hidden" name="item_valor_1" value="'.number_format($total, 2, '.', '').'" />
<input type="hidden" name="item_frete_1" value="0" />
<input type="hidden" name="cliente_nome" value="'.$firstname.' '.$lastname.'" />
<input type="hidden" name="cliente_cep" value="'.$postcode.'" />
<input type="hidden" name="cliente_num" value="'.str_replace(" ","",ereg_replace("[^a-zA-Z0-9 .]","",
                                                                        strtr($params['clientdetails']['address1'],"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,.",
                                                                        "                                                     "))).'" />
<input type="hidden" name="cliente_ddd" value="'.substr($phone, 0, 2).'" />
<input type="hidden" name="cliente_tel" value="'.str_replace("-", "", str_replace(" ", "", substr($phone, 2))).'" />
<input type="hidden" name="cliente_email" value="'.$email.'" />
<input type="submit" value="Pagar agora">
</form>';

	return $code;
}

?>