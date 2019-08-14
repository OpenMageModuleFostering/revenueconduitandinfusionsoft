<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Sales
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Order API
 *
 * @category   RevenueConduit
 * @package    RevenueConduit_RevenueConduit_Sales
 * @author     Parag Jagdale (Revenue Conduit)
 */
class RevenueConduit_RevenueConduit_Model_Sales_Order_Api_V2 extends Mage_Sales_Model_Order_Api
{

function getAllCustomerGroups(){
    //get all customer groups
    $customerGroupsCollection = Mage::getModel('customer/group')->getCollection();
    $customerGroupsCollection->addFieldToFilter('customer_group_code',array('nlike'=>'%auto%'));
//    $customerGroupsCollection->load();
    $groups = array();
    foreach ($customerGroupsCollection as $group){
    $groups[] = $group->getId();
    }
    return $groups;
}   

function getAllWbsites(){
    //get all wabsites
    $websites = Mage::getModel('core/website')->getCollection();
    $websiteIds = array();
    foreach ($websites as $website){
    $websiteIds[] = $website->getId();
    }
    return $websiteIds;
}   


public function  generate_coupon($data = array())
{
$coupon_data = $data->filter;
foreach($coupon_data as $val){
        ${$val->key} = $val->value;
}

$amount = $discount_amount_value;
$label = $name = $coupon_label;
$labels[0] = $label;//default store label
$coupon = Mage::getModel('salesrule/rule');
    $coupon->setName($name)
    ->setDescription($name)
    ->setFromDate($from_date)
    ->setToDate($to_date)
    ->setCouponCode($coupon_code)
    ->setUsesPerCoupon(1)
    ->setUsesPerCustomer(1)
    ->setCustomerGroupIds($this->getAllCustomerGroups()) //an array of customer grou pids
    ->setIsActive(1)
    //serialized conditions.  the following examples are empty
    ->setConditionsSerialized('a:6:{s:4:"type";s:32:"salesrule/rule_condition_combine";s:9:"attribute";N;s:8:"operator";N;s:5:"value";s:1:"1";s:18:"is_value_processed";N;s:10:"aggregator";s:3:"all";}')
    ->setActionsSerialized('a:6:{s:4:"type";s:40:"salesrule/rule_condition_product_combine";s:9:"attribute";N;s:8:"operator";N;s:5:"value";s:1:"1";s:18:"is_value_processed";N;s:10:"aggregator";s:3:"all";}')
    ->setStopRulesProcessing(0)
    ->setIsAdvanced(1)
    ->setProductIds('')
    ->setSortOrder(0)
    ->setSimpleAction($discount_type)
    ->setDiscountAmount($amount)
    ->setDiscountQty(null)
    ->setDiscountStep('0')
    ->setSimpleFreeShipping('0')
    ->setApplyToShipping('0')
    ->setIsRss(0)
    ->setWebsiteIds($this->getAllWbsites())
    ->setCouponType(2)
    ->setStoreLabels($labels)
    ;
if( $coupon->save() )
	return true;
else
	return false;
}




  public function order_count()
  {
    $_orders = Mage::getModel('sales/order')->getCollection();                        
    $_orderCnt = $_orders->count(); //orders count
    return $_orderCnt;
  }

public function listOfOrderNumbers($filters = null){
	$page = 0;
	$count = null;
	foreach ($filters->complex_filter as $field => $condition) {
		if($condition->key == "page"){
			$page = $condition->value->value;
			unset($filters->complex_filter->field);
		}
		if($condition->key == "count"){
			$count = $condition->value->value;
			unset($filters->complex_filter->field);
		}
	}

	/** @var $orderCollection Mage_Sales_Model_Mysql4_Order_Collection */
	$orderCollection = Mage::getModel("sales/order")->getCollection();

	if(!empty($count)){
		if(empty($page)){
			$page = 1;   
		}       
		$orderCollection->setPage($page,$count);
	}       

	$orderCollection->setOrder('entity_id', 'ASC')
		->getSelect()
		->reset(Zend_Db_Select::COLUMNS)
		->columns('increment_id');

	$orders = array();
	foreach ($orderCollection as $order) {
		$tmp = $this->_getAttributes($order, 'order');
		if(!empty($tmp['increment_id'])){
			$orders[] = $tmp['increment_id'];
		}
		unset($tmp);
	}
	return $orders;
}

  public function light_items($filters = null)
  {
  
    $start = 0;
    $count = FALSE;    
    foreach ($filters->complex_filter as $field => $condition) {
	if($condition->key == "start"){
	  $start = $condition->value->value;
	  unset($filters->complex_filter->field);
	}
	if($condition->key == "count"){
	  $count = $condition->value->value;
	  unset($filters->complex_filter->field);
	}	
    }
	      
        $orders = array();

        //TODO: add full name logic
        $billingAliasName = 'billing_o_a';
        $shippingAliasName = 'shipping_o_a';

        /** @var $orderCollection Mage_Sales_Model_Mysql4_Order_Collection */
        $orderCollection = Mage::getModel("sales/order")->getCollection()->setOrder('entity_id', 'ASC');

        $orderCollection->getSelect()
        ->reset(Zend_Db_Select::COLUMNS)
        ->columns('increment_id')
        ->columns('entity_id')
        ->columns('customer_id')
        ->columns('store_id')
        ->columns('created_at')
        ->columns('updated_at')
        ->columns('status')
        ->columns('state');


        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $this->parseFilters($filters, $this->_attributesMap['order']);
        try {
            foreach ($filters as $field => $value) {
		if($field == 'start' || $field == 'count') continue;
                $orderCollection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }

        foreach ($orderCollection as $order) {
	    if($start){
	      $start--;
	      continue;
	    }
            $orders[] = $this->_getAttributes($order, 'order');
            if($count !== FALSE && count($orders) == $count){
	      break;
            }
        }
        return $orders;
  }

    /**
     * Retrieve list of orders. Filtration could be applied
     *
     * @param null|object|array $filters
     * @return array
     */
    public function items($filters = null)
    {
        $orders = array();

        //TODO: add full name logic
        $billingAliasName = 'billing_o_a';
        $shippingAliasName = 'shipping_o_a';

        /** @var $orderCollection Mage_Sales_Model_Mysql4_Order_Collection */
        $orderCollection = Mage::getModel("sales/order")->getCollection();
        $billingFirstnameField = "$billingAliasName.firstname";
        $billingLastnameField = "$billingAliasName.lastname";
        $shippingFirstnameField = "$shippingAliasName.firstname";
        $shippingLastnameField = "$shippingAliasName.lastname";
        $orderCollection->addAttributeToSelect('*')
            ->addAddressFields()
            ->addExpressionFieldToSelect('billing_firstname', "{{billing_firstname}}",
                array('billing_firstname' => $billingFirstnameField))
            ->addExpressionFieldToSelect('billing_lastname', "{{billing_lastname}}",
                array('billing_lastname' => $billingLastnameField))
            ->addExpressionFieldToSelect('shipping_firstname', "{{shipping_firstname}}",
                array('shipping_firstname' => $shippingFirstnameField))
            ->addExpressionFieldToSelect('shipping_lastname', "{{shipping_lastname}}",
                array('shipping_lastname' => $shippingLastnameField))
            ->addExpressionFieldToSelect('billing_name', "CONCAT({{billing_firstname}}, ' ', {{billing_lastname}})",
                array('billing_firstname' => $billingFirstnameField, 'billing_lastname' => $billingLastnameField))
            ->addExpressionFieldToSelect('shipping_name', 'CONCAT({{shipping_firstname}}, " ", {{shipping_lastname}})',
                array('shipping_firstname' => $shippingFirstnameField, 'shipping_lastname' => $shippingLastnameField)
        );

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $this->parseFilters($filters, $this->_attributesMap['order']);
        try {
            foreach ($filters as $field => $value) {
                $orderCollection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        foreach ($orderCollection as $order) {
            $orders[] = $this->_getAttributes($order, 'order');
        }
        return $orders;
    }

    public function customer_count()
    {
      $_customers = Mage::getModel('customer/customer')->getCollection();                        
      $_customerCnt = $_customers->count(); //customers count
      return $_customerCnt;
    }    
    
    public function product_count()
    {
      $_products = Mage::getModel('catalog/product')->getCollection();                        
      $_productCnt = $_products->count(); //customers count
      return $_productCnt;
    } 
    
    public function product_category_count()
    {
      $_products_categories = Mage::getModel('catalog/category')->getCollection();                        
      $_productCategoryCnt = $_products_categories->count(); //customers count
      return $_productCategoryCnt;
    }
    public function customer_get_subscription_status($email){
      $_subscribers = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

      return $_subscribers->isSubscribed();
    }
    
    /**
     * Parse filters and format them to be applicable for collection filtration
     *
     * @param null|object|array $filters
     * @param array $fieldsMap Map of field names in format: array('field_name_in_filter' => 'field_name_in_db')
     * @return array
     */
    public function parseFilters($filters, $fieldsMap = null)
    {
        // if filters are used in SOAP they must be represented in array format to be used for collection filtration
        if (is_object($filters)) {
            $parsedFilters = array();
            // parse simple filter
            if (isset($filters->filter) && is_array($filters->filter)) {
                foreach ($filters->filter as $field => $value) {
                    if (is_object($value) && isset($value->key) && isset($value->value)) {
                        $parsedFilters[$value->key] = $value->value;
                    } else {
                        $parsedFilters[$field] = $value;
                    }
                }
            }
            // parse complex filter
            if (isset($filters->complex_filter) && is_array($filters->complex_filter)) {
                $parsedFilters += $this->_parseComplexFilter($filters->complex_filter);
            }

            $filters = $parsedFilters;
        }
        // make sure that method result is always array
        if (!is_array($filters)) {
            $filters = array();
        }
        // apply fields mapping
        if (isset($fieldsMap) && is_array($fieldsMap)) {
            foreach ($filters as $field => $value) {
                if (isset($fieldsMap[$field])) {
                    unset($filters[$field]);
                    $field = $fieldsMap[$field];
                    $filters[$field] = $value;
                }
            }
        }
        return $filters;
    }

    /**
     * Parses complex filter, which may contain several nodes, e.g. when user want to fetch orders which were updated
     * between two dates.
     *
     * @param array $complexFilter
     * @return array
     */
    protected function _parseComplexFilter($complexFilter)
    {
        $parsedFilters = array();

        foreach ($complexFilter as $filter) {
            if (!isset($filter->key) || !isset($filter->value)) {
                continue;
            }

            list($fieldName, $condition) = array($filter->key, $filter->value);
            $conditionName = $condition->key;
            $conditionValue = $condition->value;
            $this->formatFilterConditionValue($conditionName, $conditionValue);

            if (array_key_exists($fieldName, $parsedFilters)) {
                $parsedFilters[$fieldName] += array($conditionName => $conditionValue);
            } else {
                $parsedFilters[$fieldName] = array($conditionName => $conditionValue);
            }
        }

        return $parsedFilters;
    }

    /**
     * Convert condition value from the string into the array
     * for the condition operators that require value to be an array.
     * Condition value is changed by reference
     *
     * @param string $conditionOperator
     * @param string $conditionValue
     */
    public function formatFilterConditionValue($conditionOperator, &$conditionValue)
    {
        if (is_string($conditionOperator) && in_array($conditionOperator, array('in', 'nin', 'finset'))
            && is_string($conditionValue)
        ) {
            $delimiter = ',';
            $conditionValue = explode($delimiter, $conditionValue);
        }
    }    
} // Class Mage_Sales_Model_Order_Api End
