<?php

/******************************************************************************************

  Adriano Goulart, 2013

******************************************************************************************/

class VindiConnector
{
  protected $api_id;
  protected $api_key;
  protected $domain;
  
  protected $test_mode;
  
  public function __construct($opt)
  {
    $this->test_mode = $opt["test_mode"];
    $this->api_id = $opt["api_id"];
    $this->api_key = $opt["api_key"];
    $this->domain = $opt["domain"]; 
  }
  
  public function retrieveAllCustomersXML($page_num = 1)
  {
    return $this->sendRequest('/customers.xml?page=' . $page_num);
  }
  
  public function retrieveCustomerXMLByID($id)
  {
    return $this->sendRequest('/customers/' . $id . '.xml');
  }
  
  public function retrieveCustomerXMLByReference($ref)
  {
    return $this->sendRequest('/customers/lookup.xml?reference=' . $ref);
  }
  
  public function retrieveSubscriptionsJsonByCustomerID($id)
  {
    return $this->sendRequest('/subscription/list?_search=true&id=' . $id);
  }	
  public function retrieveSubscriptionsJsonBySubscriptionID($id)
  {
    return $this->sendRequest('/subscription/get?SUBSCRIPTION_ID=' . $id);
  }

  public function retrieveProductJsonByID($id)
  {
    return $this->sendRequest('/product/get?PRODUCT_ID=' . $id);
  }
  public function retrieveAllProductsJson()
  {
    //    return $this->sendRequest('/products.xml');
    return $this->sendRequest('/product/list');
  }
  
  /*
    Example post xml:     
    
    <?xml version="1.0" encoding="UTF-8"?>
    <subscription>
    <product_handle>' . $product_id . '</product_handle>
    <customer_attributes>
    <first_name>first</first_name>
    <last_name>last</last_name>
    <email>email@email.com</email>
    </customer_attributes>
    <credit_card_attributes>
    <first_name>first</first_name>
    <last_name>last</last_name>
    <billing_address>1 Infinite Loop</billing_address>
    <billing_city>Somewhere</billing_city>
    <billing_state>CA</billing_state>
    <billing_zip>12345</billing_zip>
    <billing_country>USA</billing_country>
    <full_number>41111111111111111</full_number>
    <expiration_month>11</expiration_month>
    <expiration_year>2012</expiration_year>
    </credit_card_attributes>
    </subscription>
  */
  /**
   * @return SimpleXMLElement|VindiSubscription
   */
  public function createCustomerAndSubscription($post_xml)
  {
    $xml = $this->sendRequest('/subscriptions.xml', $post_xml);

    $tree = new SimpleXMLElement($xml);

    if(isset($tree->error)) { return $tree; }
    else { $subscription = new VindiSubscription($tree); }
    
    return $subscription;
  }
  
  public function getAllCustomers()
  {
    $xml = $this->retrieveAllCustomersXML();
    
    $all_customers = new SimpleXMLElement($xml);
    
    $customer_objects = array();
    
    foreach($all_customers as $customer)
      {
	$temp_customer = new VindiCustomer($customer);
	array_push($customer_objects, $temp_customer);
      }
    
    return $customer_objects;
  }
  
  /**
   * @return VindiCustomer
   */
  public function getCustomerByID($id)
  {
    $xml = $this->retrieveCustomerXMLByID($id);
    
    $customer_xml_node = new SimpleXMLElement($xml);
    
    $customer = new VindiCustomer($customer_xml_node);
    
    return $customer;
  }
  
  /**
   * @return VindiCustomer
   */
  public function getCustomerByReference($ref)
  {
    $xml = $this->retrieveCustomerXMLByReference($ref);

    $customer_xml_node = new SimpleXMLElement($xml);
    
    $customer = new VindiCustomer($customer_xml_node);
    
    return $customer;
  }
  
  public function getSubscriptionsByCustomerID($id)
  {
    $json = $this->retrieveSubscriptionsJsonByCustomerID($id);

    $subscriptions = json_decode($json, true);
    
    $subscription_objects = array();
    
    foreach($subscriptions as $subscription)
      {
	$temp_sub = new VindiSubscription($subscription);
	
	array_push($subscription_objects, $temp_sub);
      }
    
    return $subscription_objects;
  }
  public function getSubscriptionsBySubscriptionID($id)
  {
    $json = $this->retrieveSubscriptionsJsonBySubscriptionID($id);

    $subscription = json_decode($json, true);
    
    $subscription_object = new VindiSubscription($subscription);
    
    return $subscription_object;
  } 
  public function getProductById($id) 
  {
    $json = $this->retrieveProductJsonByID($id);

    $product = json_decode($json, true);

    $product_object = new VindiProduct($product);

    return $product_object;
  }
  public function getAllProducts()
  {
    $json = $this->retrieveAllProductsJson();
    
    //    $all_products = new SimpleXMLElement($xml);
    $all_products = json_decode($json, true);

    $product_objects = array();

    if (!empty($all_products)) {
      foreach($all_products as $family)
	{
	  $temp_family = new VindiFamily($family);
	  foreach($temp_family->getProducts() as $product) {
	    array_push($product_objects, $product);
	  }
	}
    }
    
    return $product_objects;
  }
  
  /**
   * @return VindiCustomer
   */
  public function createCustomer($post_xml) {
    $xml = $this->sendRequest('/customers.xml', $post_xml);
    
    $customer_xml_node = new SimpleXMLElement($xml);
    
    $customer = new VindiCustomer($customer_xml_node);
    
    return $customer;
  }

  public function cancelSubscription($id) {
    return $this->sendRequest('/subscription/cancel?SUBSCRIPTION_ID=' . $id);
  }

  protected function sendRequest($uri, $post_xml = null,$method = null) {    

    $apiUrl = vindi::vindiProtocol."://{$this->domain}.".vindi::vindiBaseDomain."/vindi/api/1.0{$uri}";
    if (strpos($uri,'?') !== false) {
      $apiUrl = "{$apiUrl}&ID={$this->api_id}&KEY={$this->api_key}";
    } else {
      $apiUrl = "{$apiUrl}?ID={$this->api_id}&KEY={$this->api_key}";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    //    curl_setopt($ch, CURLOPT_USERPWD,$this->api_key.':x');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
    curl_setopt($ch, CURLOPT_HEADER , 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
    if($post_xml)
      {
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_xml);
	curl_setopt($ch, CURLOPT_HTTPHEADER , array('Content-Type: application/xml'));
      }
    if($method == 'delete')
      {
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      }
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $xml = curl_exec($ch);
    curl_close($ch);
    return $xml;
  }
}
