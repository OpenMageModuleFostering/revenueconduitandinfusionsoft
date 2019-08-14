<?php
/**
 * RevenueConduit
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA available
 * through the world-wide-web at this URL:
 * http://revenueconduit.com/magento/license
 *
 * MAGENTO EDITION USAGE NOTICE
 *
 * This package is designed for Magento COMMUNITY edition.
 * =================================================================
 *
 * @package    RevenueConduit
 * @copyright  Copyright (c) 2012-2013 RevenueConduit. (http://www.revenueconduit.com)
 * @license    http://revenueconduit.com/magento/license
 * @terms      http://revenueconduit.com/magento/terms
 * @author     Parag Jagdale
 */

class RevenueConduit_RevenueConduit_Model_Observer{

  var $_infusionsoftConnectionInfo = array();

  public function load(){
  }

  public function initialize($observer){

    if (!($observer->getEvent()->getBlock() instanceof Mage_Adminhtml_Block_Page)) {
      return;
    }
    return $this;
  }

  public function SendRequest($topic, $orderId, $customerId, $productId=0, $categoryId=0, $quoteId = 0, $original_quote_id = 0, $storeId = 0, $websiteId = 0, $contact_email = null){
    $affiliate = 0;
    $hubspotutk = 0;

    $company_app_name = '';
    $store_name = '';
    $is_admin = false;

    $client = new Varien_Http_Client();

    if(Mage::app()->getStore()->isAdmin())
    {
      $is_admin = true;
      $company_app_name = $this->getStoreConfig('revenueconduit_app_name', 'revenueconduit_revenueconduit_settings_group', $storeId, $websiteId, $is_admin);
      $store_name = $this->getStoreConfig('revenueconduit_store_name', 'revenueconduit_revenueconduit_settings_group', $storeId, $websiteId, $is_admin);
    }else{
      $company_app_name = $this->getStoreConfig('revenueconduit_app_name');
      $store_name = $this->getStoreConfig('revenueconduit_store_name');
    }

    $storeId = Mage::app()->getStore()->getStoreId();
    $url = Mage::getStoreConfig('web/unsecure/base_link_url', $storeId);

    $host = "https://app.revenueconduit.com/magento_incoming_requests/receive";
//	$host = "https://7hktcoxjyked.runscope.net";
	//$host = "https://hcbooju196rh.runscope.net";
    
    $parameter = array("company_app_name" => $company_app_name, "store_name" => $store_name, 'domain' => $url);

    if(!empty($_COOKIE) && array_key_exists('affiliate', $_COOKIE) && !$is_admin){
      $affiliate = $_COOKIE['affiliate'];
    }

    if(!empty($_COOKIE) && array_key_exists('hubspotutk', $_COOKIE) and ( $topic == "orders/create" or $topic == "orders/updated" or $topic == "customers/create" or $topic == "customers/update" or $topic == "checkouts/create" or $topic == "checkouts/delete") and !$is_admin){
      $hubspotutk = $_COOKIE['hubspotutk'];
    }

    $postParameters = array("topic" => $topic, "order_id" => $orderId, "customer_id" => $customerId, "product_id" => $productId, 'category_id' => $categoryId, 'referral_code_id' => $affiliate ? $affiliate : 0, 'quote_id' => $quoteId, 'original_quote_id' => $original_quote_id, 'hubspotutk'=> $hubspotutk, 'contact_email' => $contact_email);

    $client->setUri($host)
      ->setConfig(array('timeout' => 30))
      ->setParameterGet($parameter)
      ->setParameterPost($postParameters)
      ->setUrlEncodeBody(false)
      ->setMethod(Zend_Http_Client::POST);

    try {
      $response = $client->request();
      return $response->getBody();
    } catch (Exception $e) {
      Mage::log("Could not send the $topic request to RevenueConduit. Error Message: " . $e->getMessage(), null);
    }
    return null;
  }

  public function beforeSave($observer){
      try{
        $dataObjectClass = get_class($observer->getEvent()->getDataObject());
        if (!empty($dataObjectClass) && $dataObjectClass == 'Mage_Catalog_Model_Product') {
          $product = $observer->getEvent()->getProduct();
          if(!empty($product) && !$product->getID()) {
            $product->isNewProduct = true;
          }
        }
      }catch(Exception $ex){
        Mage::log("Could not send a product update request to RevenueConduit.", null);
      }

    return $this;
  }
  public function beforeCategorySave($observer){
      try{
	      $dataObjectClass = get_class($observer->getEvent()->getDataObject());
        if (!empty($dataObjectClass) && $dataObjectClass == 'Mage_Catalog_Model_Category') {
            $category = $observer->getEvent()->getCategory();
          if(!empty($category) && !$category->getID()) {
            $category->isNewCategory = true;
          }
        }
      }catch(Exception $ex){
        Mage::log("Could not send a product update request to RevenueConduit.", null);
      }

    return $this;
  }

  public function beforeCustomerSave($observer){
      try{
	      $dataObjectClass = get_class($observer->getEvent()->getDataObject());
        if (!empty($dataObjectClass) && $dataObjectClass == 'Mage_Customer_Model_Customer') {
            $customer = $observer->getEvent()->getCustomer();
          if(!empty($customer) && !$customer->getID()) {
            $customer->isNewCustomer = true;
          }
        }
      }catch(Exception $ex){
        Mage::log("Could not send a product update request to RevenueConduit.", null);
      }

    return $this;
  }  
  public function UpdateProduct($observer){

    try{
      if(!Mage::registry('prevent_product_observer')):
      $dataObjectClass = get_class($observer->getEvent()->getDataObject());
      if (!empty($dataObjectClass) && $dataObjectClass == 'Mage_Catalog_Model_Product') {
      	$product = $observer->getEvent()->getProduct();
        if(!empty($product) && $product->getID()) {
        $storeIds = $product->getStoreIds();
    	  if($product->isNewProduct)
          {
              $topic = "products/create";
          }else{
              $topic = "products/update";
          }
        if(!empty($storeIds))
          {
              foreach($storeIds as $storeId)
              {
                  $codeFromSite = $this->SendRequest($topic, 0, 0, $product->getID(), 0, 0, 0, $storeId, $product->getWebsiteId());
              }

          }else{
              $codeFromSite = $this->SendRequest($topic, 0, 0, $product->getID(), 0, 0, 0, $product->getStoreId(), $product->getWebsiteId());
          }
          Mage::register('prevent_product_observer',true);
        }
        }
        endif;
      }catch(Exception $ex){
        Mage::log("Could not send a product update request to RevenueConduit.", null);
      }

    return $this;
  }
  public function UpdateCategory($observer){

    try{
      $dataObjectClass = get_class($observer->getEvent()->getDataObject());
      if (!empty($dataObjectClass) && $dataObjectClass == 'Mage_Catalog_Model_Category') {
    	  $category = $observer->getEvent()->getCategory();
    	  if($category->isNewCategory) {
    	    $codeFromSite = $this->SendRequest("categories/create", 0, 0, 0, $category->getID(), 0, 0, $category->getStoreId(), $category->getWebsiteId());
        }
    	  else {
    	    $codeFromSite = $this->SendRequest("categories/update", 0, 0, 0, $category->getID(), 0, 0, $category->getStoreId(), $category->getWebsiteId());
        }
      }
    }catch(Exception $ex){
      Mage::log("Could not send a category update request to RevenueConduit.", null);
    }

    return $this;
  }
  public function UpdateCustomer($observer){
    try{
      if(!Mage::registry('prevent_customer_observer')):
        $dataObjectClass = get_class($observer->getEvent()->getDataObject());
        if (!empty($dataObjectClass) && $dataObjectClass == 'Mage_Customer_Model_Customer') {
          $customer = $observer->getEvent()->getCustomer();
          if (!empty($customer) && $customer->getId()) {
            if($customer->isNewCustomer) {
              $codeFromSite = $this->SendRequest("customers/create", 0, $customer->getId(), 0, 0, 0, 0, $customer->getStoreId(), $customer->getWebsiteId());
            }else {
              $codeFromSite = $this->SendRequest("customers/update", 0, $customer->getId(), 0, 0, 0, 0, $customer->getStoreId(), $customer->getWebsiteId());
            }
            Mage::register('prevent_customer_observer',true);
          }
        }
      endif;
    }catch(Exception $ex){
      Mage::log("Could not send a customer created request to RevenueConduit.", null);
    }
    return $this;
  }
  
  public function CreateContactRecord($observer){
    $dataObjectClass = get_class($observer->getEvent()->getDataObject());
    if (!empty($dataObjectClass) && $dataObjectClass == 'Mage_Customer_Model_Customer') {
      $customer = $observer->getEvent()->getCustomer();
      try{
        if (!empty($customer) && $customer->getId()) {
          $codeFromSite = $this->SendRequest("customers/create", 0, $customer->getId(), 0, 0, 0, 0, $customer->getStoreId(), $customer->getWebsiteId());
        }
      }catch(Exception $ex){
        Mage::log("Could not send a customer created request to RevenueConduit.", null);
      }
    }
    return $this;
  }

  public function AssignOrderSequenceOnCheckout($observer){

    $orderId = 0;
    $customerId = 0;

    $order = $observer->getOrder();
    if(!empty($order) && $order->getRealOrderId()) {
      $orderId = $order->getRealOrderId();
      $storeId = $order->getStoreId();
      $websiteId = $order->getWebsiteId();

      if(Mage::getSingleton('customer/session')->isLoggedIn()) {
          $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
      }

      try{
              $codeFromSite = $this->SendRequest("orders/create", $orderId, $customerId, 0, 0, 0, 0, $storeId, $websiteId);
      }catch(Exception $ex){
              Mage::log("Could not send an orders/create request to RevenueConduit. The order Id is: " .  $orderId, null);
      }
    }
    return $this;
  }

  public function OnOrderUpdate($observer){
    try{
      if(!Mage::registry('prevent_order_observer')):
      $dataObjectClass = get_class($observer->getEvent()->getDataObject());

      if(!empty($dataObjectClass) && $dataObjectClass == 'Mage_Sales_Model_Order'){
        $order = $observer->getEvent()->getOrder();
      }elseif(!empty($dataObjectClass) && $dataObjectClass == 'Mage_Sales_Model_Order_Invoice'){
        $order = $observer->getEvent()->getInvoice()->getOrder();
      }else {
          return $this;
      }

	Mage::log(print_r($dataObjectClass,true), null, "test123.log");

      if(!empty($order) && $order->getIncrementId()) {
        $codeFromSite = $this->SendRequest("orders/updated", $order->getIncrementId()."test", null, 0, 0, 0, 0, $order->getStoreId(), $order->getWebsiteId());
	Mage::register('prevent_order_observer',true);
      }
      endif;
    }catch(Exception $ex){
      Mage::log("Could not send an order created request to RevenueConduit. " . $ex->getMessage() , null);
    }
    return $this;
  } 

  public function salesOrderShipmentSaveAfter($observer){
    try{
      if(!Mage::registry('prevent_order_observer')):
        $dataObjectClass = get_class($observer->getEvent()->getDataObject());

        if(!empty($dataObjectClass) && $dataObjectClass == 'Mage_Sales_Model_Order'){
          $order = $observer->getEvent()->getOrder();
        }elseif(!empty($dataObjectClass) && $dataObjectClass == 'Mage_Sales_Model_Order_Shipment'){
          $order = $observer->getEvent()->getShipment()->getOrder();
        }else {
          return $this;
        }
        if(!empty($order) && $order->getIncrementId()) {
          $codeFromSite = $this->SendRequest("orders/updated", $order->getIncrementId(), null, 0, 0, 0, 0, $order->getStoreId(), $order->getWebsiteId());
	Mage::register('prevent_order_observer',true);
        }
      endif;
    }catch(Exception $ex){
      Mage::log("Could not send an order updated request to RevenueConduit. " . $ex->getMessage() , null);
    }
    return $this;
  }

  public function salesOrderHoldChange($observer){
    try{
      if(!Mage::registry('prevent_order_observer')):
      $dataObjectClass = get_class($observer->getEvent()->getDataObject());
        if(!empty($dataObjectClass) && $dataObjectClass == 'Mage_Sales_Model_Order'){
          $order = $observer->getEvent()->getOrder();
          $stateProcessing = Mage_Sales_Model_Order::STATE_HOLDED;
          if(!empty($order) && !empty($stateProcessing) && ($order->getState() == $stateProcessing || $order->getOrigData('state') == $stateProcessing)) {
            $codeFromSite = $this->SendRequest("orders/updated", $order->getIncrementId(), null, 0, 0, 0, 0, $order->getStoreId(), $order->getWebsiteId());
            Mage::register('prevent_order_observer',true);
          }
        }
      endif;
    }catch(Exception $ex){
      Mage::log("Could not send an order updated request to RevenueConduit. " . $ex->getMessage() , null);
    }
    return $this;
  }
  
    public function captureReferral(Varien_Event_Observer $observer)
    {
        // here we add the logic to capture the referring affiliate ID
	$frontController = $observer->getEvent()->getFront();        
	if(!empty($frontController)){
		$affiliateID = $frontController->getRequest()->getParam('affiliate', false);
		if(!empty($affiliateID)){
			$getHostname = null;
			$getHostname = Mage::app()->getFrontController()->getRequest()->getHttpHost();
			setcookie("affiliate", $affiliateID, time()+3600, '/', $getHostname);
		}
	}
    }  

  /*
   * Gets the data from the store configuration
   * @param string keyName - the string which is the key that identifies the value that is requested
   */
  public function getStoreConfig($keyName = null, $group = "revenueconduit_revenueconduit_settings_group", $storeId = 0, $websiteId = 0, $is_admin = false){
    if(!empty($keyName)){
      if(!empty($storeId)) {
          $value = Mage::getStoreConfig("revenueconduit_revenueconduit_options/$group/" . $keyName, $storeId);
      }elseif(!empty($websiteId) && $is_admin) {
          $value = Mage::app()->getWebsite($websiteId)->getConfig("revenueconduit_revenueconduit_options/$group/" . $keyName);
      }else {
          $value = Mage::getStoreConfig("revenueconduit_revenueconduit_options/$group/" . $keyName);
      }

      if(!empty($storeId) && empty($value) && $is_admin && !empty($websiteId))
      {
          $value = Mage::app()->getWebsite($websiteId)->getConfig("revenueconduit_revenueconduit_options/$group/" . $keyName);
      }

      if(empty($value) && $is_admin)
      {
          $value = Mage::getStoreConfig("revenueconduit_revenueconduit_options/$group/" . $keyName);
      }

      if(!empty($value)){
        return trim($value);
      }else{
        return $value;
      }
    }else{
      return null;
    }
  }

  public function setStoreConfig($keyName, $value){
    Mage::getModel('core/config')->saveConfig('revenueconduit_revenueconduit_options/revenueconduit_revenueconduit_group/' . $keyName, $value );
  }


  public function autoRegisterBilling($evt){

	$configValue = Mage::getStoreConfig('revenueconduit_revenueconduit_options/revenueconduit_revenueconduit_settings_group_abandoned/revenueconduit_abandoned_cart_setting',Mage::app()->getStore());
		if($configValue)
		{
			// retrieve quote items collection
			$itemsCollection = Mage::getSingleton('checkout/session')->getQuote()->getItemsCollection();

			// get array of all items what can be display directly
			$itemsVisible = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
			$quoteId = 0;
      $original_quote_id = 0;
			// retrieve quote items array
			$items = Mage::getSingleton('checkout/session')->getQuote()->getAllItems();

			$quote_info =  Mage::getSingleton('checkout/session')->getQuote();

			$quoteId = $quote_info->getEntityId();
			$original_quote_id = Mage::getSingleton('core/session')->getOriginalQuoteId();

			if(!empty($quoteId)){
        $codeFromSite = $this->SendRequest("checkouts/create", 0, 0, 0, 0, $quoteId, $original_quote_id );
      }

		}//if
           }
	
  public function checkoutsDelete()
  {
    $quoteId = 0;
    $original_quote_id = 0;
    $configValue = Mage::getStoreConfig('revenueconduit_revenueconduit_options/revenueconduit_revenueconduit_settings_group_abandoned/revenueconduit_abandoned_cart_setting',Mage::app()->getStore());
    if($configValue)
    {
			$quote_info =  Mage::getSingleton('checkout/session')->getQuote();
      $original_quote_id = Mage::getSingleton('core/session')->getOriginalQuoteId();

      $quoteId = $quote_info->getEntityId();
      if(!empty($quoteId)){
        $codeFromSite = $this->SendRequest("checkouts/delete", 0, 0, 0, 0, $quoteId , $original_quote_id);
      }
		}//if
  }
}
