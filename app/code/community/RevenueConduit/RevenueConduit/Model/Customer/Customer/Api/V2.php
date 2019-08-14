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
/**
 * Customer Soap Api V2 
 */
class RevenueConduit_RevenueConduit_Model_Customer_Customer_Api_V2 extends Mage_Customer_Model_Customer_Api_V2
{    
    /**
     * Retrieve cutomers data
     * Extended filters capability for customerCustomerList
     * 
     * @param  array $filters
     * @return array
     */
    public function items($filters)
    {
        $collection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*');

        $preparedFilters = array();        
        
        if (isset($filters->filter)) {
            foreach ($filters->filter as $_filterKey => $_filterValue) {                                
                if (is_object($_filterValue)) { 
                    $preparedFilters[][$_filterValue->key] = $_filterValue->value;                    
                } else {                    
                    $preparedFilters[][$_filterKey] = $_filterValue;
                }
            }
        }
        
        if (isset($filters->complex_filter)) {
            foreach ($filters->complex_filter as $_key => $_filter) {
                $_value = $_filter->value;
                if(is_object($_value)) {
                    $preparedFilters[][$_filter->key] = array(
                    $_value->key => $_value->value
                );
                } elseif(is_array($_value)) {
                    $preparedFilters[][$_key] = array(
                        $_filter->key => $_value
                    );
                } else {
                    $preparedFilters[][$_filter->key] = $_value;
                }
            }
        }

        if (!empty($preparedFilters)) {
            try {
                foreach ($preparedFilters as $preparedFilter) {
                    foreach ($preparedFilter as $field => $value) {
                        if (isset($this->_mapAttributes[$field])) {
                            $field = $this->_mapAttributes[$field];
                        }

                        $collection->addFieldToFilter($field, $value);
                    }
                }     
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }        
        
        $result = array();
        foreach ($collection as $customer) {
            $data = $customer->toArray();
            $row  = array();

            foreach ($this->_mapAttributes as $attributeAlias => $attributeCode) {
                $row[$attributeAlias] = (isset($data[$attributeCode]) ? $data[$attributeCode] : null);
            }

            foreach ($this->getAllowedAttributes($customer) as $attributeCode => $attribute) {
                if (isset($data[$attributeCode])) {
                    $row[$attributeCode] = $data[$attributeCode];
                }
            }

            $result[] = $row;
        }
        
        return $result;        
    }
    
    public function customer_count()
    {
      $_customers = Mage::getModel('customer/customer')->getCollection();                        
      $_customerCnt = $_customers->count(); //customers count
      return $_customerCnt;
    }    
}
		