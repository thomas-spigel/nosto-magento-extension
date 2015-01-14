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
 * @category    Nosto
 * @package     Nosto_Tagging
 * @copyright   Copyright (c) 2013-2015 Nosto Solutions Ltd (http://www.nosto.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once(Mage::getBaseDir('lib').'/nosto/php-sdk/src/config.inc.php');

/**
 * History data export controller.
 * Handles the export of history data for orders and products that nosto can call when a new account has been set up.
 * The exported data is encrypted with AES as the endpoint is publicly available.
 *
 * @category    Nosto
 * @package     Nosto_Tagging
 * @author      Nosto Solutions Ltd
 */
class Nosto_tagging_ExportController extends Mage_Core_Controller_Front_Action
{
	/**
	 * Exports completed orders from the current store.
	 * Result can be limited by the `limit` and `offset` GET parameters.
	 */
	public function orderAction()
	{
		if (Mage::helper('nosto_tagging')->isModuleEnabled()) {
			$pageSize = (int)$this->getRequest()->getParam('limit', 100);
			$currentPage = (int)$this->getRequest()->getParam('offset', 0) + 1;
			$orders = Mage::getModel('sales/order')
				->getCollection()
				->addFieldToFilter('store_id', Mage::app()->getStore()->getId())
				->addAttributeToFilter('status', Mage_Sales_Model_Order::STATE_COMPLETE)
				->setPageSize($pageSize)
				->setCurPage($currentPage);
			if ($currentPage > $orders->getLastPageNumber()) {
				$orders = array();
			}
			$collection = new NostoExportOrderCollection();
			foreach ($orders as $order) {
				$meta = new Nosto_Tagging_Model_Meta_Order();
				$meta->loadData($order);
				$collection[] = $meta;
			}
			$this->export($collection);
		}
	}

	/**
	 * Exports visible products from the current store.
	 * Result can be limited by the `limit` and `offset` GET parameters.
	 */
	public function productAction()
	{
		if (Mage::helper('nosto_tagging')->isModuleEnabled()) {
			$pageSize = (int)$this->getRequest()->getParam('limit', 100);
			$currentPage = (int)$this->getRequest()->getParam('offset', 0) + 1;
			$products = Mage::getModel('catalog/product')
				->getCollection()
				->addStoreFilter(Mage::app()->getStore()->getId())
				->addAttributeToSelect('*')
				->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED))
				->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
				->setPageSize($pageSize)
				->setCurPage($currentPage);
			if ($currentPage > $products->getLastPageNumber()) {
				$products = array();
			}
			$collection = new NostoExportProductCollection();
			foreach ($products as $product) {
				if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE
					&& (int)$product->getPriceType() === Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED
				) {
					continue;
				}
				$meta = new Nosto_Tagging_Model_Meta_Product();
				$meta->loadData($product);
				$collection[] = $meta;
			}
			$this->export($collection);
		}
	}

	/**
	 * Encrypts the export collection and outputs it to the browser.
	 *
	 * @param NostoExportCollection $collection the data collection to export.
	 */
	protected function export(NostoExportCollection $collection)
	{
		$account = Mage::helper('nosto_tagging/account')->find();
		if ($account !== null) {
			$cipher_text = NostoExporter::export($account, $collection);
			echo $cipher_text;
		}
		die();
	}
}
