<?php

//Reference Documentation: http://support.vindi.com/faqs/api/api-subscriptions

class VindiSubscription extends VindiConnector
{
  private $customer;
  private $product;
  private $credit_card;
  
  private $state;
  private $balance_in_cents;
  private $current_period_started_at;
  private $current_period_ends_at;
  private $trial_started_at;
  private $trial_ends_at;
  private $activated_at;
  private $expires_at;
  private $created_at;
  private $updated_at;

  // -----------------------------------------------------------

  private $subscriptionId;
  private $productId;
  private $status;
  private $billingDate;
  private $discountFixedAmount;
  private $discountPercentAmount;
  private $discountTemporary;
  private $discountTemporaryRemainingCycles;
  private $trialRange;
  private $trialPeriod;
  private $trialNoObligation;
  private $trialEnd;
  private $currentInstallment;
  private $totalInstallments;
  private $recurringRange;
  private $lastCharge;
  private $nextCharge;
  private $trialStart;
  private $start;
  private $test;

  public function __construct(array $subscription_node)
  {    
    //Load object dynamically and convert SimpleXMLElements into strings
    foreach($subscription_node as $key => $element)
    {
      if($key == 'customer') { $this->customer = new VindiCustomer($element); }
      else if($key == 'product') { $this->product = new VindiProduct($element); }
      else if($key == 'credit_card') { $this->credit_card = new VindiCreditCard($element); }
      else { $this->$key = (string)$element; }
    }
  }
  
  protected function format_timestamp($format, $timestamp)
  {
    $temp = explode('T', $timestamp);
    $temp = strtotime($temp[0]);
    
    return date($format, $temp);
  }
  
  
  /* Getters */

  public function getId() { return $this->subscriptionId; }
  
  public function getCustomer() { return $this->customer; }
  
  public function getProduct() { return $this->product; }
  
  public function getCreditCard() { return $this->credit_card; }
  
  public function getState() { return $this->status;  }
  
  public function getBalanceInCents() { return $this->balance_in_cents; }
  
  public function getCurrentPeriodStart($date_format = NULL)
  { 
    if($date_format == NULL) { return $this->current_period_started_at; }
    else { return $this->format_timestamp($date_format, $this->current_period_started_at); }
  }
  
  public function getCurrentPeriodEnd() { return $this->current_period_ends_at; }
  
  public function getTrialStart() { return $this->trial_started_at; }
  
  public function getTrialEnd() { return $this->trial_ends_at; }
  
  public function getActivatedAt() { return $this->activated_at; }
  
  public function getExpiresAt() { return $this->expires_at; }
  
  public function getCreatedAt($date_format = NULL)
  {
    if($date_format == NULL) { return $this->created_at; }
    else { return $this->format_timestamp($date_format, $this->created_at); }
  }
  
  public function getUpdatedAt() { return $this->updated_at; }

  public function getProductId() { return $this->productId; }
}

