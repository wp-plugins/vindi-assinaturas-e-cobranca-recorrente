<?php

class VindiFamily extends VindiConnector
{
  private $productFamilyId;
  private $productFamilyName;
  private $products = array();

  public function __construct(array $family_node)
  {
    foreach($family_node as $key => $element) {
      if ($key == 'products') {
	foreach($element as $product) {
	  $temp_product = new VindiProduct($product);
	  array_push($this->products, $temp_product);
	}
      } else {
	$this->$key = (string)$element;
      }
    }
    foreach($this->products as $prod) {
      $prod->setFamilyId($this->productFamilyId);
    }
  }

  /* Getters */
  public function getProductFamilyId() { return $this->productFamilyId; }
  public function getProductFamilyName() { return $this->productFamilyName; }
  public function getProducts() { return $this->products; }

}
  