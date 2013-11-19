<?php

class VindiCustomer extends VindiConnector
{
  private $id;
  private $created_at;
  private $email;
  private $first_name;
  private $last_name;
  private $organization;
  private $reference;
  private $updated_at;

  // --------------------------------
  private $customerId;
  private $customerName;
  private $customerEmail;
  private $dtSince;
  private $customerRef;
  
  public function __construct(array $customer_node)
  {  
    //Load object dynamically and convert 
    foreach($customer_node as $key => $element) { 
      if ($key == 'creditCards') { 
  //"creditCards":[{"id":95,"creditCardNumber":"**** **** **** 2762","flag":"VISA"}],"":null},	
      } else {
        $this->$key = (string)$element; 
      }
    }
  }
  
  
  /* Getters - I like to do this the old-fashioned way */
  
  public function getID() { return $this->customerId; }
  
  public function getCreatedAt() { return $this->created_at; }
  
  public function getFirstName() { return $this->first_name; }
  
  public function getLastName() { return $this->last_name; }
  
  public function getOrganization() { return $this->organization; }
  
  public function getReference() { return $this->reference; }
  
  public function getUpdatedAt() { return $this->updated_at; }
  
  public function getFullName() { return $this->first_name . ' ' . $this->last_name; }

  // ---------------------------------

  public function getEmail() { return $this->customerEmail; }
}

?>