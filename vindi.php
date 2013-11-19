<?php
/*
Plugin Name: Vindi Wordpress Plugin
Plugin URI: http://vindi.com.br/plugins
Description: Gerencie assinaturas no WordPress usando a API da Vindi
Author: Vindi
Version: 1.0.0
Author URI: http://vindi.com.br
*/

$base = dirname(__FILE__);

include($base.'/lib/VindiConnector.php');
include($base.'/lib/VindiCreditCard.php');
include($base.'/lib/VindiCustomer.php');
include($base.'/lib/VindiProduct.php');
include($base.'/lib/VindiFamily.php');
include($base.'/lib/VindiSubscription.php');

add_shortcode('vindi', array('vindi','subscriptionListShortCode'));
add_action('admin_menu',array('vindi','control'));
add_filter('the_posts',array('vindi','checkAccess'));
add_action('admin_menu', array('vindi','createMetaAccessBox'));  
add_action('save_post', array('vindi','metaAccessBoxSave'));  
add_filter('init', array('vindi','subscriptionRedirect'));  
add_filter('the_content', array('vindi','subscriptionPost'));  
add_action('show_user_profile', array('vindi','userActions'));
add_action('edit_user_profile', array('vindi','userActions'));
add_action('profile_update', array('vindi','userActionsUpdate'));
register_activation_hook(__FILE__,array("vindi","activate"));
register_deactivation_hook(__FILE__,array("vindi","deactivate"));

class vindi
{
  //const vindiProtocol = "https";
  const vindiProtocol = "http";

  //const vindiBaseDomain = "vindi.com.br";
  const vindiBaseDomain = "hexxie.com";

  function manualTrim($text)
  {
    $text = strip_shortcodes( $text );

    $text = apply_filters('the_content', $text);
    $text = str_replace(']]>', ']]&gt;', $text);
    $text = strip_tags($text);
    $excerpt_length = apply_filters('excerpt_length', 55);
    $excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
    $words = explode(' ', $text, $excerpt_length + 1);
    if (count($words) > $excerpt_length) {
      array_pop($words);
      $text = implode(' ', $words);
      $text = $text . $excerpt_more;
    }
    return $text;
  }
  function checkAccess($posts)
  {
    $user = wp_get_current_user();
    if($user->roles[0] == 'administrator')
      {
	return $posts;
      }		
    
    $user = wp_get_current_user();
    if($user->roles[0] == 'administrator')
      {
	return $posts;
      }		
    
    foreach($posts as $k => $post)
      {
	$vindi = get_option('vindi');
	$d = get_post_meta($post->ID, 'vindi_access', true);
	$u = wp_get_current_user();
	if(!empty($d) && is_array($d["levels"]))
	  {
	    if(!in_array($u->vindi_level,$d["levels"]))
	      {
		switch($vindi["vindiNoAccessAction"])
		  {
		  case 'excerpt':
		    $post->post_content = strlen(trim($post->post_excerpt)) ? $post->post_excerpt : vindi::manualTrim($post->post_content);
		    break;
		  default:
		    $post->post_content = $vindi["vindiDefaultNoAccess"]; 
		  }
	      }
	    $post->post_content .= '<br><br>Efetue seu login ou <a href="'.$vindi["vindiSignupLink"].'"><strong>Assine</strong></a> para ver esta página.';
	  }
	
	$posts[$k] = $post;
      }
    return $posts;
  }
  function products()
  {
    $d = get_option('vindi');
    $opt = array("api_id" => $d["vindiApiId"],"api_key" => $d["vindiApiKey"],"domain" => $d["vindiDomain"],"test_mode"=>($d["vindiMode"] == 'test'? TRUE : FALSE));	
    $connector = new VindiConnector($opt);
    $products = $connector->getAllProducts();

    return $products;
  }

  function subscriptionListShortCode($atts)
  {
    extract(shortcode_atts(array('accountingcodes'=>''), $atts));
    $filteraccountingcodes = array();
    if ($accountingcodes != '') {
      $acs = explode(',', $accountingcodes);
      for($i = 0; $i < count($acs); $i++) {
	$filteraccountingcodes[$acs[$i]] = true;
      }
    }
    $d = get_option("vindi");
    if($d["vindiSignupType"] == 'api')
      {	
	$monthDrop = '<select style="width:50px" name="vindiSignupExpMo">';
	for($i=1; $i<13; $i++)
	  {
	    $monthDrop .= '<option value="'.$i.'" '.($_POST["vindiSignupExpMo"] == $i ? "selected" : "").'>'.$i.'</option>';
	  }
	$monthDrop .= '</select>';
	$yearDrop = '<select style="width:70px" name="vindiSignupExpYr">';
	for($i=(int)date("Y"); $i < (int)date("Y",strtotime("+10 years")); $i++)
	  {
	    $yearDrop .= '<option value="'.$i.'" '.($_POST["vindiSignupExpYr"] == $i ? "selected" : "").'>'.$i.'</option>';
	  }
	$yearDrop .= '</select>';
	
	$products = vindi::products();
	$form ='<form name="vindiSignupForm" method="post" action="">
			<input type="hidden" name="vindi_signupcc_noncename" id="vindi_signupcc_noncename" value="'.wp_create_nonce( plugin_basename(__FILE__) ).'" />
			<input type="hidden" name="submit" value="">	
			<table>
				<tr>
					<th colspan="2"><p><strong>Informações do Assinante</strong></p></th>
				</tr>
				<tr>
					<td>Nome</td>
					<td><input type="text" name="vindiSignupFirst" value="'.$_POST["vindiSignupName"].'"></td>
				</tr>
				<tr>
					<td>Email</td>
					<td><input type="text" name="vindiSignupEmail" value="'.$_POST["vindiSignupEmail"].'"></td>
				</tr>
				<tr>
					<th colspan="2">Informações de Pagamento</th>
				</tr>
				<tr>
					<td>Billing Last Name</td>
					<td><input type="text" name="vindiSignupBillLast" value="'.$_POST["vindiSignupBillLast"].'"></td>
				</tr>
				<tr>
					<td>Billing Address</td>
					<td><input type="text" name="vindiSignupBillAddress" value="'.$_POST["vindiSignupBillAddress"].'"></td>
				</tr>
				<tr>
					<td>Billing City</td>
					<td><input type="text" name="vindiSignupBillCity" value="'.$_POST["vindiSignupBillCity"].'"></td>
				</tr>
				<tr>
					<td>Billing State</td>
					<td><input type="text" name="vindiSignupBillState" value="'.$_POST["vindiSignupBillState"].'"></td>
				</tr>
				<tr>
					<td>Billing Zip Code</td>
					<td><input type="text" name="vindiSignupBillZip" value="'.$_POST["vindiSignupBillZip"].'"></td>
				</tr>
				<tr>
					<td>Nome no Cartão de Crédito</td>
					<td><input type="text" name="vindiSignupBillFirst" value="'.$_POST["vindiSignupBillFirst"].'"></td>
				</tr>
				<tr>
					<td>Número do Cartão de Crédito</td>
					<td><input type="text" name="vindiSignupBillCc" value="'.$_POST["vindiSignupBillCc"].'"></td>
				</tr>
				<tr>
					<td>Validade do Cartão</td>
					<td>Month: '.$monthDrop.'<br>Year:'.$yearDrop.'</td>
				</tr>	
<input type="hidden" name="vindiSignupProduct"/>
				';
	$productdisplayed = 0;
	foreach($products as $p)
	  {
	    if ((isset($filteraccountingcodes[$p->getAccountCode()]) && $filteraccountingcodes[$p->getAccountCode()]) || count($filteraccountingcodes) == 0) {
	      $form .= '<tr>';
	      $form .= '<td><div align="center"><strong><p>'.$p->getName().'</strong><br>R$ '.$p->getFmtPrice().' '.($p->getInterval() == 1 ? ' por '.$p->getIntervalUnit() : ' a cada '.$p->getInterval().' '.$p->getIntervalUnit().'s').'<br>'.$p->description.'</p></div></td>';
	      $form .= '<td><p><input onclick="javascript:document.vindiSignupForm.submit.value=\''.$p->getFamilyId().'\';document.vindiSignupForm.vindiSignupProduct.value=\''.$p->getHandle().'\';" name="submit'.$p->getHandle().'" type="submit" value="'.$p->getName().'"></p></td>';
	      $form .= '</tr>';
	      $productdisplayed = 1;
	    }
	  }
	if(!$productdisplayed)
	  {
	    $form = '<form name="vindiSignupForm" method="post" action=""><table><tr><td colspan="2">Nenhum plano encontrado</td></tr>';
	  }

	$form .= '</table>
			</form>';
      }
    else
      {
	$form ='<form name="vindiSignupForm" method="post" action="">
			<input type="hidden" name="vindi_signup_noncename" id="vindi_signupcc_noncename" value="'.wp_create_nonce( plugin_basename(__FILE__) ).'" />
			<input type="hidden" name="submit" value="">	
			<table>
				<tr>
					<th colspan="2"><p><strong>Informações do Assinante</strong></p></th>
				</tr>
				<tr>
					<td>Nome</td>
					<td><input type="text" name="vindiSignupName" value="'.$_POST["vindiSignupName"].'"></td>
				</tr>
				<tr>
					<td>Email</td>
					<td><input type="text" name="vindiSignupEmail" value="'.$_POST["vindiSignupEmail"].'"></td>
				</tr>
				<tr>
					<th colspan="2"><p><strong>Planos</strong></p></th>
				</tr>
<input type="hidden" name="vindiSignupProduct"/>
				';
	
	$products = vindi::products();
	$productdisplayed = 0;
	foreach($products as $p)
	  {
	    if ((isset($filteraccountingcodes[$p->getAccountCode()]) && $filteraccountingcodes[$p->getAccountCode()]) || count($filteraccountingcodes) == 0) {
	      $form .= '<tr>';
	      $form .= '<td><div align="center"><strong><p>'.$p->getName().'</strong><br>R$ '.$p->getFmtPrice().' '.($p->getInterval() == 1 ? ' por '.$p->getIntervalUnit() : ' a cada '.$p->getInterval().' '.$p->getIntervalUnit().'s').'<br>'.$p->description.'</p></div></td>';
	      $form .= '<td><p><input onclick="javascript:document.vindiSignupForm.submit.value=\''.$p->getFamilyId().'\';document.vindiSignupForm.vindiSignupProduct.value=\''.$p->getHandle().'\';" name="submit'.$p->getHandle().'" type="submit" value="'.$p->getName().'"></p></td>';
	      $form .= '</tr>';
	      $productdisplayed = 1;
	    }
	  }
	if(!$productdisplayed)
	  {
	    $form = '<form name="vindiSignupForm" method="post" action=""><table><tr><td colspan="2">Nenhum plano encontrado</td></tr>';
	  }
	$form .= '</table>';
	$form .= '</form>';


      }
    return $form;
  }
  function userActionsUpdate($user_id)
  {
    if($_POST["vindiCancelSubscription"])
      {
	$d = get_option('vindi');
	$opt = array("api_id" => $d["vindiApiId"],"api_key" => $d["vindiApiKey"],"domain" => $d["vindiDomain"],"test_mode"=>($d["vindiMode"] == 'test'? TRUE : FALSE));	
	$connector = new VindiConnector($opt);
	$connector->cancelSubscription($_POST["vindiCancelSubscription"]);
      }
  }
  function userActions($u)
  {
    if(!strlen($u->vindi_custid))
      return 0;

    echo '<h3>Assinatura Vindi</h3>';
    $d = get_option('vindi');
    $opt = array("api_id" => $d["vindiApiId"],"api_key" => $d["vindiApiKey"],"domain" => $d["vindiDomain"],"test_mode"=>($d["vindiMode"] == 'test'? TRUE : FALSE));	
    $connector = new VindiConnector($opt);
    $sub = $connector->getSubscriptionsByCustomerID($u->vindi_custid);
    if(is_array($sub))
      {
	foreach($sub as $s)
	  {
	    $prod = $connector->getProductById($s->getProductId());
	    echo '<strong>'.$prod->getName().'</strong><br>R$ '.$prod->getFmtPrice().' '.($prod->getInterval() == 1 ? ' por  '.$prod->getIntervalUnit() : ' a cada '.$prod->getInterval().' '.$prod->getIntervalUnit().'s').'<br>'.$prod->description;
            echo '<br>Subscription Status:<strong> '.$s->getState().'</strong><br/>';
	    // TODO - activate 
	    if($s->getState() == 'LIVE_ACTIVE' || $s->getState() == 'LIVE_TRIAL')
	      echo '<input type="checkbox" name="vindiCancelSubscription" value="'.$s->getId().'"><strong> Check this box to cancel this subscription</strong>';
	  }
      }
  }
  function metaAccessBox()
  {
    global $post;
    
    $d = get_post_meta($post->ID, 'vindi_access', true); 

    if (!empty($d)) {
      $levels = $d["levels"];
    }
    
    $products = vindi::products();
    $form = '<strong>Planos que podem acessar este conteúdo : </strong> <br/> Nota: Se você não escolher nenhum plano abaixo, esta será uma página <strong>pública<strong><br>';
    $form .= '<input type="hidden" name="access_noncename" id="access_noncename" value="'.wp_create_nonce( plugin_basename(__FILE__) ).'" />';
    foreach($products as $p)
      {
	$form .= '<input type="checkbox" name="vindiAccess['.$p->getHandle().']" value="'.$p->getHandle().'" '.($levels[$p->getHandle()] ? "checked" : "").'> '.$p->getName().'<br>';
      }
    echo $form;
  }	
  
  function subscriptionRedirect()
  {
    if ( wp_verify_nonce( $_POST['vindi_signup_noncename'], plugin_basename(__FILE__) ) && is_numeric($_POST["submit"]))
      {	
	if(!vindi::check_email_address($_POST["vindiSignupEmail"]) || !strlen($_POST["vindiSignupName"]) )
	  {
	    $_POST["vindi_signup_error"] = array('ERROR'=>"Todos os campos são obrigatórios. Por favor digite um nome e um endereço de email válido");
	    return 0;
	  }
	
	$d = get_option("vindi");
	require_once( ABSPATH . WPINC . '/registration.php');	
	$user_login = sanitize_user( $_POST["vindiSignupEmail"] );
	$user_email = apply_filters( 'user_registration_email', $_POST["vindiSignupEmail"] );
	if(username_exists($user_login) || email_exists($user_email))
	  {
	    $_POST["vindi_signup_error"] = array('ERROR'=>"O endereço de email já está em uso, por favor escolha outro.");
	    return 0;
	  }
	else
	  {
	    $user_pass = wp_generate_password();
	    $d[$_POST["vindiSignupEmail"]]["user_login"] = $user_login;
	    $d[$_POST["vindiSignupEmail"]]["user_email"] = $user_email;
	    $d[$_POST["vindiSignupEmail"]]["user_pass"] = $user_pass;
	    update_option("vindi",$d);

	    $uri = '?p='.urlencode($_POST["vindiSignupProduct"]).'&n='.urlencode($_POST["vindiSignupName"]).'&e='.urlencode($_POST["vindiSignupEmail"]).'&r='.urlencode($_POST["vindiSignupEmail"]);
	    if($d["vindiMode"] == 'test')
	      {
		$uri = $uri.'&test=true';
	      }

	    header("Location: ".vindi::vindiProtocol."://".$d["vindiDomain"].".".vindi::vindiBaseDomain."/default/".$_POST["submit"]."/".$uri);
	    exit;
	  }
      }
    // Aqui trata um retorno post vindo da Vindi
    if(function_exists('json_decode') && $_SERVER["CONTENT_TYPE"] === 'application/json')
      {
	echo "DEBUG - TESTE - RETORNO";
	global $wpdb;
	$sub_ids = json_decode(file_get_contents('php://input'));
	file_put_contents("/tmp/postback",print_r($sub_ids,true),FILE_APPEND);
	if($sub_ids !== NULL && is_array($sub_ids))
	  {
	    $d = get_option('vindi');
	    $opt = array("api_id" => $d["vindiApiId"],"api_key" => $d["vindiApiKey"],"domain" => $d["vindiDomain"],"test_mode"=>($d["vindiMode"] == 'test'? TRUE : FALSE));	
	    $connector = new VindiConnector($opt);
	    foreach($sub_ids as $id)
	      {
		$sub = $connector->getSubscriptionsBySubscriptionId($id);
		if($sub->getStatus() == 'canceled')
		  {
		    $cur = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->usermeta WHERE meta_key = 'vindi_custid' AND meta_value = %s", $sub->getCustomer()->getId() ) );
		    if ( $cur && $cur->user_id )
		      {
			delete_usermeta( $cur->user_id, 'vindi_level'); 
		      }
		  }			
	      }
	  }
      }
  }

  function subscriptionPost($the_content)
  {
    $d = get_option("vindi");

    //Process full CC single form
    if ( wp_verify_nonce( $_POST['vindi_signupcc_noncename'], plugin_basename(__FILE__) ))
      {  
	$d = get_option('vindi');
	$opt = array("api_id" => $d["vindiApiId"],"api_key" => $d["vindiApiKey"],"domain" => $d["vindiDomain"],"test_mode"=>($d["vindiMode"] == 'test'? TRUE : FALSE));	
	$connector = new VindiConnector($opt);
	$xml = '<?xml version="1.0" encoding="UTF-8"?>
			<subscription>
				<product_id>' . $_POST["submit"] . '</product_id>
				<customer_attributes>
				<first_name>'.$_POST["vindiSignupName"].'</first_name>
				<email>'.$_POST["vindiSignupEmail"].'</email>
				</customer_attributes>
				<credit_card_attributes>
				<first_name>'.$_POST["vindiSignupBillFirst"].'</first_name>
				<last_name>'.$_POST["vindiSignupBillLast"].'</last_name>
				<billing_address>'.$_POST["vindiSignupBillAddress"].'</billing_address>
				<billing_city>'.$_POST["vindiSignupBillCity"].'</billing_city>
				<billing_state>'.$_POST["vindiSignupBillState"].'</billing_state>
				<billing_zip>'.$_POST["vindiSignupBillZip"].'</billing_zip>
				<full_number>'.$_POST["vindiSignupBillCc"].'</full_number>
				<expiration_month>'.$_POST["vindiSignupExpMo"].'</expiration_month>
				<expiration_year>'.$_POST["vindiSignupExpYr"].'</expiration_year>
				</credit_card_attributes>
			</subscription>';
	
	require_once( ABSPATH . WPINC . '/registration.php');	
	$user_login = sanitize_user( $_POST["vindiSignupEmail"] );
	$user_email = apply_filters( 'user_registration_email', $_POST["vindiSignupEmail"] );
	if(username_exists($user_login) || email_exists($user_email))
	  {
	    return "That email address is already in use, please choose another.".$the_content;
	  }
	else
	  {
	    $res = $connector->createCustomerAndSubscription($xml);
	    if(strlen($res->error))
	      {
		return '<strong>'.$res->error.'</strong><br><br>'.$the_content;
	      }
	    else
	      {
		$user_pass = wp_generate_password();
		$user_id = wp_create_user( $user_login, $user_pass, $user_email );
		wp_new_user_notification($user_id, $user_pass);
		update_usermeta( $user_id, 'vindi_level', $res->getProduct()->getHandle()); 
		update_usermeta( $user_id, 'vindi_custid', $res->getCustomer()->getId()); 
		return $d["vindiThankYou"];
	      }
	  }	
	
      }

    //check to see if there was an error in the form processing step in vindiRedirect
    if(is_array($_POST["vindi_signup_error"]) && isset($_POST["vindi_signup_error"]['ERROR']))
      {
	return $_POST["vindi_signup_error"]['ERROR'].$the_content;
      }

    if(isset($_GET["vindi"]) && $_GET["subscriptionId"] && !isset($_REQUEST["vindi.subscriptionPost"]))
      {
	$d = get_option('vindi');
	$opt = array("api_id" => $d["vindiApiId"],"api_key" => $d["vindiApiKey"],"domain" => $d["vindiDomain"],"test_mode"=>($d["vindiMode"] == 'test'? TRUE : FALSE));	
	$connector = new VindiConnector($opt);
	$sub = $connector->getSubscriptionsBySubscriptionId($_GET["subscriptionId"]);
	if($sub->getState() == 'LIVE_ACTIVE' || $sub->getState() == 'LIVE_TRIAL')
	  {
	    $email = $sub->getCustomer()->getEmail(); 
	    if(isset($d[$email]))
	      {
		require_once( ABSPATH . WPINC . '/registration.php');
		$user_id = wp_create_user( $d[$email]["user_login"], $d[$email]["user_pass"], $d[$email]["user_email"] );
		if(is_wp_error($user_id))
		  {
		    return $user_id->get_error_message();
		  }
		else
		  {
		    //It's possible to hit this section twice depending on configuration
		    //this ensures that it won't do all this work twice
		    //it's a filthy hack but it works for now
		    $_REQUEST["vindi.subscriptionPost"] = $user_id;
		    wp_new_user_notification($user_id, $d[$email]["user_pass"]);

		    update_usermeta( $user_id, 'vindi_level', $sub->getProductId()); 
		    update_usermeta( $user_id, 'vindi_custid', $sub->getCustomer()->getId());
		    return $d["vindiThankYou"];
		  }
	      }
	  }
      }	
    return $the_content;
  }
  function metaAccessBoxSave($post_id)
  {
    if ( !wp_verify_nonce( $_POST['access_noncename'], plugin_basename(__FILE__) ))
      {  
	return $post_id;  
      }
    if ( 'page' == $_POST['post_type'] ) 
      {
	if ( !current_user_can( 'edit_page', $post_id ))
	  {
	    return $post_id;
	  }
      } 
    else 
      {
	if ( !current_user_can( 'edit_post', $post_id ))
	  {
	    return $post_id;
	  }
      }

    $data["levels"] = $_POST['vindiAccess'];
    
    update_post_meta($post_id, 'vindi_access', $data);
  }
  
  

  function activate()
  {
    $data = array(
		  'vindiApiId'=>'APIID',
		  'vindiApiKey'=>'APIKEY',
		  'vindiDomain'=>'domain',
		  'vindiMode'=>'test',
		  'vindiSignupType'=>'default',
		  'vindiNoAccessAction'=>'default',
		  'vindiDefaultNoAccess'=>'Voce nao tem permissao para acessar esta pagina. Por favor, cadastre sua conta para visualizar este conteudo.',
		  'vindiThankYou'=>'<strong>Assinatura criada com sucesso!</strong>',
		  'vindiSignupLink'=>'<<coloque o link para sua pagina de login aqui>>'
		  );
    
    if(!get_option("vindi"))
      {
	add_option("vindi",$data);
      }
  }

  function deactivate()
  {
    delete_option("vindi");
  }

  function control()
  {
    add_options_page('Vindi Options','Vindi','activate_plugins','vindi-admin-settings',array('vindi','controlForm'));
  }

  function controlForm()
  {
    $d = get_option("vindi");

    if($_REQUEST["vindiForm"] == 'Y')
      {
	$d["vindiApiId"] = $_REQUEST["vindiApiId"];
	$d["vindiApiKey"] = $_REQUEST["vindiApiKey"];
	$d["vindiDomain"] = $_REQUEST["vindiDomain"];
	$d["vindiMode"] = $_REQUEST["vindiMode"];
	$d["vindiNoAccessAction"] = $_REQUEST["vindiNoAccessAction"];
	$d["vindiDefaultNoAccess"] = $_REQUEST["vindiDefaultNoAccess"];
	$d["vindiThankYou"] = $_REQUEST["vindiThankYou"];
	$d["vindiSignupLink"] = $_REQUEST["vindiSignupLink"];
	$d["vindiSignupType"] = $_REQUEST["vindiSignupType"];

	update_option('vindi',$d);

	echo '<div class="updated"><p><strong>Options saved</strong></p></div>';
      }

    echo '<div class="wrap">
            <form name="vindi" method="post" action="">
            <input type="hidden" name="vindiForm" value="Y">
            <h2>Vindi Settings</h2>
            <h3>Informações da Conta</h3>
            <p><em>Recupere esta informação da sua conta da Vindi.</em></p>
            <table class="form-table"> 
                <tr valign="top"> 
                    <th scope="row"><label>API ID:</label></th>
                    <td><input type="text" size="40" name="vindiApiId" value="'.$d['vindiApiId'].'"></td>
                </tr>

                <tr valign="top"> 
                    <th scope="row"><label>API Key:</label></th>
                    <td><input type="text" size="40" name="vindiApiKey" value="'.$d['vindiApiKey'].'"></td>
                </tr>
                <tr valign="top"> 
                    <th scope="row"><label>Dominio:</label></th>
                    <td><input type="text" size="40" name="vindiDomain" value="'.$d['vindiDomain'].'">.vindi.com.br</td>
                </tr>
                <tr valign="top"> 
                    <th scope="row"><label>Mode:</label></th>
                    <td><input type="radio" name="vindiMode" value="test" '.($d['vindiMode'] == 'test' ? 'checked' : '').'>Teste <input type="radio" name="vindiMode" value="live" '.($d['vindiMode'] == 'live' ? 'checked' : '').'>Produção</td>
                </tr>   

            </table>
            <hr />
            <h3>Tipo de Assinatura</h3>
            <p><em>Como seu site irá processar novas assinaturas. <strong>NOTA: Em dúvida, deixe esta configuração no padrão</strong>.</em></p>
            <table class="form-table"> 
                <tr valign="top"> 
                    <th scope="row"><input type="radio" name="vindiSignupType" value="default" '.($d['vindiSignupType'] == 'default' ? 'checked' : '').'>Padrão</th>
                    <td>Quando um usuário cria uma assinatura, ele será direcionado à Vindi para digitar as informações de pagamento e será redirecionado de volta a este site para ver a página de sucesso</td>
                </tr>
                <!-- tr valign="top"> 
                    <th scope="row"><input type="radio" name="vindiSignupType" value="api" '.($d['vindiSignupType'] == 'api' ? 'checked' : '').'>API Style</th>
                    <td><strong>Opção Avançada: </strong>Quando um usuário cria uma assinatura ele deverá digitar a informação de pagamento neste site e a nova conta será criada sem o usuário sair do site.<strong><br />IMPORTANTE: Uma vez que as informações de cartão de crédito estarão passando por dentro deste site neste modo, você não deve ativá-lo sem possuir um certificado SSL em seu site! Você deve estar apto a criar forms e trabalhar com programação API.</td>
                </tr -->
            </table>
            <hr />   

            <h3>Mensagem de "Sem Acesso"</h3>
            <p><em>Mensagem para informar o usuário que ele não possui o acesso correto para acessar o conteúdo.</em></p>
            <table class="form-table"> 
                <tr valign="top">           
                    <th scope="row"><input type="radio" name="vindiNoAccessAction" value="default" '.($d["vindiNoAccessAction"] == "default" ? "checked" : "").'> Exibe mensagem customizada</th>
                    <td><textarea cols=40 rows=3 style="display:inline-block; vertical-align:middle "name="vindiDefaultNoAccess">'.$d['vindiDefaultNoAccess'].'</textarea></td>           
                </tr>
                <tr valign="top">           
                    <th scope="row"><input type="radio" name="vindiNoAccessAction" value="excerpt" '.($d["vindiNoAccessAction"] == "excerpt" ? "checked" : "").'> Exibe mensagem na página</th>
                    <td>&nbsp;</td>         
                </tr>
                </table>
                <hr />
                <h3>Link de Assinatura</h3>
                <p><em>Digite a URL para a página do site que irá iniciar o processo de assinatura. Você deverá criar esta página no Wordpress.</em></p>
                <table class="form-table">      
                <tr valign="top">           
                    <th scope="row"><label>Link to signup page.</label></th>
                    <td><input type="text" size="60" name="vindiSignupLink" value="'.$d['vindiSignupLink'].'"><br/><span class="description">Esta página será exibida quando o usuário não possuir acesso ao conteúdo.</span></td>            
                </tr>
            </table>            
            <hr />
            <h3>Mensagem de Sucesso</h3>
            <table class="form-table">              
                <tr valign="top">           
                    <th scope="row"><label>Página de Sucesso após uma assinatura bem sucedida.</label></th>
                    <td><textarea cols=40 rows=3 name="vindiThankYou">'.$d['vindiThankYou'].'</textarea></td>         
                </tr>
            </table>

            <hr />
            <p class="submit"><input type="submit" name="Submit" value="Gravar Opções" /></p>
            </form>
        </div>';
  }
  function createMetaAccessBox() 
  {
    add_meta_box( 'new-meta-boxes', 'Vindi Access Settings', array('vindi','metaAccessBox'), 'post', 'normal', 'high' );
    add_meta_box( 'new-meta-boxes', 'Vindi Access Settings', array('vindi','metaAccessBox'), 'page', 'normal', 'high' );
  }

  function check_email_address($email)
  {
    // First, we check that there's one @ symbol, and that the lengths are right
    if (!ereg("^[^@]{1,64}@[^@]{1,255}$", $email))
      {
	// Email invalid because wrong number of characters in one section, or wrong number of @ symbols.
	return false;
      }
    // Split it into sections to make life easier
    $email_array = explode("@", $email);
    $local_array = explode(".", $email_array[0]);
    for ($i = 0; $i < sizeof($local_array); $i++)
      {
	if (!ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$", $local_array[$i]))
	  {
	    return false;
	  }
      }
    if (!ereg("^\[?[0-9\.]+\]?$", $email_array[1]))
      { // Check if domain is IP. If not, it should be valid domain name
	$domain_array = explode(".", $email_array[1]);
	if (sizeof($domain_array) < 2)
	  {
	    return false; // Not enough parts to domain
	  }
	for ($i = 0; $i < sizeof($domain_array); $i++)
	  {
	    if (!ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$", $domain_array[$i]))
	      {
		return false;
	      }
	  }
      }   
    return true;
  }
}
?>
	