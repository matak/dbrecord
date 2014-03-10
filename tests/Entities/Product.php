<?php



namespace dbrecord\Test\Entities;

use dbrecord\Entity;


class ProductVariationsException extends \Exception {};
class ProductVariationsHashAttributeException extends ProductVariationsException {};
class ProductVariationsHashSamplesException extends ProductVariationsException {};

/**
 * Product
 *
 * @author Roman Matěna
 * @copyright Copyright (c) 2010-2014 Roman Matěna (http://www.romanmatena.cz)
 *
 * @Entity(repositoryClass = "\dbrecord\Test\Entities\ProductRepository", validatorClass ="\dbrecord\Test\Entities\ProductRepository")
 * @Table(name = "solis__products", mainIndex = "#locales.name")
 *
 * @Column(name = "id", type = "i", size = 11, nullable = false, default = NULL, primary = true, autoincrement = true)
 * @Column(name = "sku", type = "s", size = 70, nullable = true, default = NULL)
 * @Column(name = "idProducer", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "idExpedition", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "idMargin", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "idDiscount", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "calculateBy", type = "i", size = 1, nullable = false, default = "1")
 * @Column(name = "listPrice", type = "f", size = 10, nullable = false, default = "0.00")
 * @Column(name = "purchasePrice", type = "f", size = 10, nullable = false, default = "0.00")
 * @Column(name = "salesPrice", type = "f", size = 10, nullable = false, default = "0.00")
 * @Column(name = "salesPriceIncVAT", type = "f", size = 10, nullable = true, default = NULL)
 * @Column(name = "VATtype", type = "i", size = 1, nullable = false, default = "3")
 * @Column(name = "guaranty", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "measureUnit", type = "s", size = 20, nullable = false, default = "ks")
 * @Column(name = "idPhe", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "pheIncluded", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "idAp", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "apIncluded", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "minBuyQuantity", type = "i", size = 11, nullable = false, default = "1")
 * @Column(name = "minBuyQuantityAfter", type = "i", size = 11, nullable = false, default = "1")
 * @Column(name = "dateInserted", type = "t", size = NULL, nullable = true, default = NULL)
 * @Column(name = "dateUpdated", type = "t", size = NULL, nullable = true, default = NULL)
 * @Column(name = "datePublished", type = "t", size = NULL, nullable = true, default = NULL)
 * @Column(name = "dateFinished", type = "t", size = NULL, nullable = true, default = NULL)
 * @Column(name = "hits", type = "i", size = 11, nullable = true, default = "0")
 * @Column(name = "visibility", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "visibilityFront", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "A", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "MA", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "D", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "PS", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "AS", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "N", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "T", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "EAN", type = "s", size = 255, nullable = true, default = NULL)
 * @Column(name = "idAlternativeGoodsPack", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "type", type = "i", size = 1, nullable = false, default = "1")
 * @Column(name = "idParameterTemplate", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "idSampleTemplate", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "variation", type = "s", size = 50, nullable = true, default = NULL)
 * @Column(name = "idStore", type = "i", size = 11, nullable = false, default = NULL)
 * @Column(name = "idSupplier", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "supplierSku", type = "s", size = 70, nullable = true, default = NULL)
 * @Column(name = "priority", type = "i", size = 11, nullable = true, default = NULL)
 * @Column(name = "hasAttributes", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "hasSamples", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "hasParameters", type = "i", size = 1, nullable = false, default = "0")
 * @Column(name = "adminNote", type = "s", size = NULL, nullable = true, default = NULL)
 * @Column(name = "heurekacz_cpc", type = "i", size = 2, nullable = false, default = "0")
 * @Column(name = "zbozicz_cpc", type = "i", size = 2, nullable = false, default = "0")
 * @Column(name = "skrz_cpc", type = "i", size = 2, nullable = false, default = "0")
 * @Column(name = "skrz_discount_text", type = "s", size = 20, nullable = true, default = NULL)
 * @Column(name = "skrz_deal_start", type = "t", size = NULL, nullable = true, default = NULL)
 * @Column(name = "skrz_deal_end", type = "t", size = NULL, nullable = true, default = NULL)
 * @Column(name = "path3d", type = "s", size = NULL, nullable = true, default = NULL)
 *
 * @AssociationHasOne(name = "producer", type = "hasOne", referenceClass = "\Models\Products\Producer", localId = "idProducer", foreignId = "id")
 * @AssociationHasOne(name = "expedition", type = "hasOne", referenceClass = "\Models\Products\Expedition", localId = "idExpedition", foreignId = "id")
 * @AssociationHasOne(name = "margin", type = "hasOne", referenceClass = "\Models\Products\ProductMargin", localId = "idMargin", foreignId = "id")
 * @AssociationHasOne(name = "discount", type = "hasOne", referenceClass = "\Models\Products\ProductDiscount", localId = "idDiscount", foreignId = "id")
 * @AssociationHasOne(name = "phe", type = "hasOne", referenceClass = "\Models\Products\ProductPhe", localId = "idPhe", foreignId = "id")
 * @AssociationHasOne(name = "ap", type = "hasOne", referenceClass = "\Models\Products\ProductAp", localId = "idAp", foreignId = "id")
 * @AssociationHasOne(name = "mainImage", type = "hasOne", referenceClass = "\Models\Products\ProductImage", localId = "id", foreignId = "idProduct", condition = "#.id = #mainImage.idProduct AND #mainImage.main = 1")
 * @AssociationHasMany(name = "images", type = "hasMany", referenceClass = "\Models\Products\ProductImage", localId = "id", foreignId = "idProduct")
 * @AssociationHasMany(name = "categories", type = "hasMany", referenceClass = "\Models\Categories\Category", localId = "id", foreignId = "id", through = "solis__products_categories", throughLocalId = "idProduct", throughForeignId = "idCategory")
 * @AssociationHasMany(name = "attributes", type = "hasMany", referenceClass = "\Models\Products\ProductAttribute", localId = "id", foreignId = "idProduct", associatedCollectionClass = "Models\Products\ProductAttributeCollection")
 * @AssociationHasMany(name = "stocks", type = "hasMany", referenceClass = "\Models\Products\ProductStock", localId = "id", foreignId = "idProduct")
 * @AssociationHasMany(name = "parameterValues", type = "hasMany", referenceClass = "\Models\Parameters\ParameterValue", localId = "id", foreignId = "id", through = "solis__products_parameters", throughLocalId = "idProduct", throughForeignId = "idValue", associatedCollectionClass = "Models\ProductParameterCollection")
 * @AssociationHasMany(name = "samples", type = "hasMany", referenceClass = "\Models\Products\ProductSample", localId = "id", foreignId = "idProduct", associatedCollectionClass = "Models\Products\ProductSampleCollection")
 * @AssociationHasMany(name = "sampleTemplateItems", type = "hasMany", referenceClass = "\Models\Products\ProductSampleTemplateItem", localId = "idSampleTemplate", foreignId = "idSampleTemplate", associatedCollectionClass = "Models\Products\ProductSampleTemplateItemCollection")
 * @AssociationHasOne(name = "parameterTemplate", type = "hasOne", referenceClass = "\Models\Parameters\ParameterTemplate", localId = "idParameterTemplate", foreignId = "id")
 * @AssociationHasMany(name = "parameterTemplateItems", type = "hasMany", referenceClass = "\Models\Parameters\ParameterTemplateItem", localId = "idParameterTemplate", foreignId = "idParameterTemplate")
 * @AssociationHasMany(name = "files", type = "hasMany", referenceClass = "\Models\Products\ProductFile", localId = "id", foreignId = "idProduct")
 * @AssociationHasMany(name = "locales", type = "hasMany", referenceClass = "\Models\Products\ProductLocale", localId = "id", foreignId = "idProduct")
 * @AssociationHasMany(name = "relatedGoods", type = "hasMany", referenceClass = "\Models\Products\Product", localId = "id", foreignId = "id", through = "solis__products_relatedgoods", throughLocalId = "idProduct", throughForeignId = "idRelated", associatedCollectionClass = "Models\Products\ProductRelatedGoodsCollection")
 * @AssociationHasMany(name = "relatedDeliveries", type = "hasMany", referenceClass = "\Models\Delivery", localId = "id", foreignId = "id", through = "solis__products_relateddeliveries", throughLocalId = "idProduct", throughForeignId = "idDelivery", associatedCollectionClass = "Models\Products\ProductRelatedDeliveriesCollection")
 * @AssociationHasOne(name = "supplier", type = "hasOne", referenceClass = "\Models\Users\UserProfileSupplier", localId = "idSupplier", foreignId = "idUser")
 *
 *
 * @property int $id
 * @property string $sku
 * @property int $idProducer
 * @property int $idExpedition
 * @property int $idMargin
 * @property int $idDiscount
 * @property int $calculateBy
 * @property float $listPrice
 * @property float $purchasePrice
 * @property float $salesPrice
 * @property float $salesPriceIncVAT
 * @property int $VATtype
 * @property int $guaranty
 * @property string $measureUnit
 * @property int $idPhe
 * @property int $pheIncluded
 * @property int $idAp
 * @property int $apIncluded
 * @property int $minBuyQuantity
 * @property int $minBuyQuantityAfter
 * @property \dbrecord\DateTime $dateInserted
 * @property \dbrecord\DateTime $dateUpdated
 * @property \dbrecord\DateTime $datePublished
 * @property \dbrecord\DateTime $dateFinished
 * @property int $hits
 * @property int $visibility
 * @property int $visibilityFront
 * @property int $A
 * @property int $MA
 * @property int $D
 * @property int $PS
 * @property int $AS
 * @property int $N
 * @property int $T
 * @property string $EAN
 * @property int $idAlternativeGoodsPack
 * @property int $type
 * @property int $idParameterTemplate
 * @property int $idSampleTemplate
 * @property string $variation
 * @property int $idStore
 * @property int $idSupplier
 * @property string $supplierSku
 * @property int $priority
 * @property int $hasAttributes
 * @property int $hasSamples
 * @property int $hasParameters
 * @property string $adminNote
 * @property int $heurekacz_cpc
 * @property int $zbozicz_cpc
 * @property int $skrz_cpc
 * @property string $skrz_discount_text
 * @property \dbrecord\DateTime $skrz_deal_start
 * @property \dbrecord\DateTime $skrz_deal_end
 * @property string $path3d
 *
 * @property \Models\Products\Producer $producer
 * @property \Models\Products\Expedition $expedition
 * @property \Models\Products\ProductMargin $margin
 * @property \Models\Products\ProductDiscount $discount
 * @property \Models\Products\ProductPhe $phe
 * @property \Models\Products\ProductAp $ap
 * @property \Models\Products\ProductImage $mainImage
 * @property \dbrecord\AssociatedCollection $images
 * @property \dbrecord\AssociatedCollection $categories
 * @property \Models\Products\ProductAttributeCollection $attributes
 * @property \dbrecord\AssociatedCollection $stocks
 * @property \Models\Products\ProductParameterCollection $parameterValues
 * @property \Models\Products\ProductSampleCollection $samples
 * @property \Models\Products\ProductSampleTemplateItemCollection $sampleTemplateItems
 * @property \Models\Parameters\ParameterTemplate $parameterTemplate
 * @property \dbrecord\AssociatedCollection $parameterTemplateItems
 * @property \dbrecord\AssociatedCollection $files
 * @property \dbrecord\AssociatedCollection $locales
 * @property \Models\Products\ProductRelatedGoodsCollection $relatedGoods
 * @property \Models\Products\ProductRelatedDeliveriesCollection $relatedDeliveries
 * @property \Models\Users\UserProfileSupplier $supplier
 *
 */
class Product// extends Entity
{
	
}