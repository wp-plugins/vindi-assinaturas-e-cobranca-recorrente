<?php

class VindiProduct extends VindiConnector
{
  private $price_in_cents;
  private $product_family = array('name' => NULL, 'handle' => NULL, 'accounting_code' => NULL);
  private $accounting_code;
  private $interval_unit;
  private $interval;
  // ----------------
  private $productId;
  private $productName;
  private $productCode;
  private $setupFee;
  private $trialRange;
  private $trialPeriod;
  private $trialNoObligation;
  private $recurringRange;
  private $price;
  private $requestCC = true;
  private $requireCC = false;
  private $requestAddress = false;
  private $requireAddress = false;
  private $nofInstallments = 0; // Qtde de parcelas
  private $installmentValue = 0.0;
  private $familyId;
  private $enabled = true;

  public function __construct(array $product_node)
  {  
    //Load object dynamically and convert array into strings
    foreach($product_node as $key => $element) {
        $this->$key = (string)$element;
    }
  }
  
  
  /* Getters */
  public function getFmtPrice() {
    return number_format($this->price, 2,',','.');
  }
  
  public function getPriceInCents() { return $this->price_in_cents; }
  
  public function getPriceInDollars() { return number_format($this->price_in_cents / 100, 0); }
  
  public function getName() { return $this->productName; }
  
  public function getHandle() { return $this->productId; }
  
  public function getProductFamily() { return $this->product_family; }
  
  public function getAccountCode() { return $this->accounting_code; }

  public function getFamilyId() { return $this->familyId; }
  
  public function getIntervalUnit() { 
    switch($this->recurringRange) {
    case 'DAYS': return 'dia';break;
    case 'WEEKS': return 'semana'; break;
    case 'MONTHS': return 'mês'; break;
    case 'TRIMESTER': return 'mês'; break;
    case 'SEMESTER': return 'mês'; break;
    case 'ANNUAL': return 'ano'; break;
    }
  }
  
  public function getInterval() {
    switch($this->recurringRange) {
    case 'DAYS': return 1;break;
    case 'WEEKS': return 1; break;
    case 'MONTHS': return 1; break;
    case 'TRIMESTER': return 3; break;
    case 'SEMESTER': return 6; break;
    case 'ANNUAL': return 1; break;
    }
  }

  public function setFamilyId($famId) {
    $this->familyId = $famId;
  }

}