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
 * Product Soap Api V2
 */

class RevenueConduit_RevenueConduit_Model_Catalog_Product_Api_V2 extends Mage_Catalog_Model_Product_Api_V2
{
    /**
     * Retrieve list of products with basic info (id, sku, type, set, name)
     * Extended filters capability for catalogProductList
     * 
     * @param array $filters
     * @param string|int $store
     * @return array
     */
    public function items($filters = null, $store = null)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($this->_getStoreId($store))
            ->addAttributeToSelect('name');

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
                        if (isset($this->_filtersMap[$field])) {
                            $field = $this->_filtersMap[$field];
                        }

                        $collection->addFieldToFilter($field, $value);
                    }
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }

        $result = array();

        foreach ($collection as $product) {
            $result[] = array(
                'product_id' => $product->getId(),
                'sku'        => $product->getSku(),
                'name'       => $product->getName(),
                'set'        => $product->getAttributeSetId(),
                'type'       => $product->getTypeId(),
                'category_ids' => $product->getCategoryIds(),
                'website_ids'  => $product->getWebsiteIds()
            );
        }
        
        return $result;        
    }
    
    public function product_count()
    {
      $_products = Mage::getModel('catalog/product')->getCollection();                        
      $_productCnt = $_products->count(); //customers count
      return $_productCnt;
    }    
    
}
		