<?php

/*
 * @ M�dulo PagSeguro com Retorno Autom�tico
 */

ob_start();

/*
 * Inclui o arquivo de configura��o do WHMCS
 * Respeite a ordem das pastas, caso contr�rio o sistema n�o ir� incluir o arquivo de configura��o do WHMCS.
 */
include("../../../configuration.php");

/*
 * Formata data
 */
function data($var){
	return date('d/m/Y H:i:s', strtotime($var));
}

/*
 * Faz a conex�o com o banco de dados
 */
mysql_select_db($db_name, mysql_connect($db_host, $db_username, $db_password)) or print (mysql_error());

/*
 * Pega o token do banco de dados
 */
$token = mysql_fetch_assoc(mysql_query("SELECT value FROM tblpaymentgateways WHERE gateway = 'pagseguro' AND setting = 'token'"));
define('TOKEN', $token[value]);

/*
 
 */
include("class.ps.php");

/*
 * Fun��o que captura os dados enviados pelo PagSeguro
 */
function retorno_automatico ($VendedorEmail, $TransacaoID, $Referencia, $TipoFrete, $ValorFrete, $Anotacao, $DataTransacao, $TipoPagamento, $StatusTransacao, $CliNome, $CliEmail, $CliEndereco, $CliNumero, $CliComplemento, $CliBairro, $CliCidade, $CliEstado, $CliCEP, $CliTelefone, $produtos, $NumItens) {

	/*
	 * Fun��o que mostra o status
	 */
	function status($string, $status, $data, $tipo, $id){
		$patterns[0] = '/%status%/';
		$patterns[1] = '/%data%/';
		$patterns[2] = '/%tipopagamento%/';
		$patterns[3] = '/%transid%/';
		$replacements[3] = $status;
		$replacements[2] = $data;
		$replacements[1] = $tipo;
		$replacements[0] = $id;
		return preg_replace($patterns, $replacements, $string);
	}

	/*
	 * Pega do banco de dados o campo status que � preenchido nas configura��es.
	 * Em seguida faz a substitui��o das variaveis %status%, %data%, %tipopagamento%, %transid% pelos seus respectivos valores.
	 */
	$status = mysql_fetch_assoc(mysql_query("SELECT value FROM tblpaymentgateways WHERE gateway='pagseguro' AND setting='status'"));
	if(empty($status)){ $status = '%status%'; }

	$function_status = status($status[value], $StatusTransacao, $DataTransacao, $TipoPagamento, $TransacaoID);

	/*
	 * Verifica se o e-mail da sua conta PagSeguro est� correto.
	 */
	$verificar_email = mysql_query("SELECT * FROM tblpaymentgateways WHERE gateway = 'pagseguro' AND setting = 'conta' AND value = '$VendedorEmail'") or die (mysql_error());
	if(mysql_num_rows($verificar_email) == 1){

		if($StatusTransacao == "Completo"){  }
		if($StatusTransacao == "Cancelado"){  }

		/*
		 * Pagamentos em an�lise. Normalmente quando s�o feitos via Cart�o de Cr�dito.
		 */
		if($StatusTransacao == "Em An�lise"){

			/*
			 * Pega o ID do cliente do banco de dados de acordo o n�mero da fatura enviada pelo PagSeguro.
			 * Em seguida insere no banco de dados o status de que o pagamento est� em an�lise.
			 */
			$id_cliente = mysql_fetch_assoc(mysql_query("SELECT userid FROM tblinvoices WHERE id='$Referencia'") or die (mysql_error()));
			mysql_query("INSERT INTO tblaccounts (userid, gateway, date, description, amountin, fees, amountout, transid, invoiceid) VALUES ('$id_cliente[userid]', 'pagseguro', '".data($DataTransacao)."', 'Invoice Payment', '0.00', '0.00', '0.00', '$function_status', '$Referencia')") or die (mysql_error());

		}

		/*
		 * Pagamento aprovado. O cliente efetuou o pagamento e o PagSeguro o recebeu.
		 */
		if($StatusTransacao == "Aprovado"){

			// Rodar API do WHMCS
			/* Pega o valor real do servi�o atrav�s do Invoice ID. */
			$verificar_status = mysql_query("SELECT * FROM tblinvoices WHERE id='$Referencia' AND status='Paid'") or die (mysql_error());
			if(mysql_num_rows($verificar_status) < 1){

				$url = mysql_fetch_assoc(mysql_query("SELECT value FROM tblconfiguration WHERE setting='SystemURL'"));
				$url = $url[value]."includes/api.php"; // Endere�o do arquivo api.php do seu WHMCS

				// Pegamos o e-mail da conta PagSeguro.
				$login = mysql_fetch_assoc(mysql_query("SELECT value FROM tblpaymentgateways WHERE gateway='pagseguro' AND setting='login'"));

				// Pegamos a senha do seu username do WHMCS j� em md5 para conectarmos no API.
				$senha = mysql_fetch_assoc(mysql_query("SELECT password FROM tbladmins WHERE username='".$login[value]."'"));

				// Pegamos o ID do cliente.
				$id_cliente = mysql_fetch_assoc(mysql_query("SELECT userid FROM tblinvoices WHERE id='$Referencia'"));

				// Pega o valor real do servi�o atrav�s do Invoice ID.
				$valor = mysql_fetch_assoc(mysql_query("SELECT total FROM tblinvoices WHERE id='$Referencia'"));

				$postfields["username"] = $login[value];
				$postfields["password"] = $senha[password];
				$postfields["action"] = "addinvoicepayment";
				$postfields["clientid"] = $id_cliente[userid];

				/* C�digo para adicionar o pagamento ao WHMCS */
				$postfields["invoiceid"] = $Referencia;
				$postfields["transid"] = $function_status;
				$postfields["amount"] = $valor[total];
				$postfields["fees"] = '0.00';
				$postfields["gateway"] = 'pagseguro';
				$postfields["noemail"] = false;

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT, 100);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
				$data = curl_exec($ch);
				curl_close($ch);

				$data = explode(";",$data);
				foreach ($data AS $temp) {
					$temp = explode("=",$temp);
					$results[$temp[0]] = $temp[1];
				}

				if ($results["result"]=="success") {
					// Sucess
				} else {
					# An error occured
					echo "The following error occured: ".$results["message"];
				}

			}

		}
		if($StatusTransacao == "Aguardando Pagto"){
			// Pega o id do cliente
			$id_cliente = mysql_fetch_assoc(mysql_query("SELECT userid FROM tblinvoices WHERE id='$Referencia'"));
			mysql_query("INSERT INTO tblaccounts (userid, gateway, date, description, amountin, fees, amountout, transid, invoiceid) VALUES ('$id_cliente[userid]', 'pagseguro', '".data($DataTransacao)."', 'Invoice Payment', '0.00', '0.00', '0.00', '$function_status', '$Referencia')") or die (mysql_error());
		}

	} else {

		// Vai enviar o erro para o error_log.
		error_log('Erro na verifica��o de e-mail.', 0);

	}

}

/*
 * Pega a p�gina de confirma��o do banco de dados que ser� redirecionada
 */
$paginaConfirmacao = mysql_fetch_assoc(mysql_query("SELECT value FROM tblpaymentgateways WHERE gateway = 'pagseguro' AND setting = 'link_conf'"));
header("Location: $paginaConfirmacao[value]");
ob_end_flush();

?>
