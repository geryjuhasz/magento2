<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Catalog\Test\Unit\Model\Product;

use Magento\Catalog\Helper\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Url;
use Magento\Catalog\Model\Product\Url as ProductUrl;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    /**
     * @var Url
     */
    protected $model;

    /**
     * @var FilterManager|MockObject
     */
    protected $filter;

    /**
     * @var UrlFinderInterface|MockObject
     */
    protected $urlFinder;

    /**
     * @var Category|MockObject
     */
    protected $catalogCategory;

    /**
     * @var \Magento\Framework\Url|MockObject
     */
    protected $url;

    /**
     * @var SidResolverInterface|MockObject
     */
    protected $sidResolver;

    protected function setUp(): void
    {
        $this->filter = $this->getMockBuilder(
            FilterManager::class
        )->disableOriginalConstructor()->setMethods(
            ['translitUrl']
        )->getMock();

        $this->urlFinder = $this->getMockBuilder(
            UrlFinderInterface::class
        )->disableOriginalConstructor()->getMock();

        $this->url = $this->getMockBuilder(
            \Magento\Framework\Url::class
        )->disableOriginalConstructor()->setMethods(
            ['setScope', 'getUrl']
        )->getMock();

        $this->sidResolver = $this->createMock(SidResolverInterface::class);

        $store = $this->createPartialMock(Store::class, ['getId', '__wakeup']);
        $store->expects($this->any())->method('getId')->will($this->returnValue(1));
        $storeManager = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $storeManager->expects($this->any())->method('getStore')->will($this->returnValue($store));

        $urlFactory = $this->getMockBuilder(UrlFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $urlFactory->method('create')
            ->willReturn($this->url);

        $objectManager = new ObjectManager($this);
        $this->model = $objectManager->getObject(
            ProductUrl::class,
            [
                'filter' => $this->filter,
                'catalogCategory' => $this->catalogCategory,
                'storeManager' => $storeManager,
                'urlFactory' => $urlFactory,
                'sidResolver' => $this->sidResolver,
            ]
        );
    }

    public function testFormatUrlKey()
    {
        $strIn = 'Some string';
        $resultString = 'some';

        $this->filter->expects(
            $this->once()
        )->method(
            'translitUrl'
        )->with(
            $strIn
        )->will(
            $this->returnValue($resultString)
        );

        $this->assertEquals($resultString, $this->model->formatUrlKey($strIn));
    }

    /**
     * @dataProvider getUrlDataProvider
     * @covers \Magento\Catalog\Model\Product\Url::getUrl
     * @covers \Magento\Catalog\Model\Product\Url::getUrlInStore
     * @covers \Magento\Catalog\Model\Product\Url::getProductUrl
     *
     * @param $getUrlMethod
     * @param $routePath
     * @param $requestPathProduct
     * @param $storeId
     * @param $categoryId
     * @param $routeParams
     * @param $routeParamsUrl
     * @param $productId
     * @param $productUrlKey
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function testGetUrl(
        $getUrlMethod,
        $routePath,
        $requestPathProduct,
        $storeId,
        $categoryId,
        $routeParams,
        $routeParamsUrl,
        $productId,
        $productUrlKey
    ) {
        $product = $this->getMockBuilder(
            Product::class
        )->disableOriginalConstructor()->setMethods(
            ['getStoreId', 'getEntityId', 'getId', 'getUrlKey', 'setRequestPath', 'hasUrlDataObject', 'getRequestPath',
                'getCategoryId', 'getDoNotUseCategoryId', '__wakeup', ]
        )->getMock();
        $product->expects($this->any())->method('getStoreId')->will($this->returnValue($storeId));
        $product->expects($this->any())->method('getCategoryId')->will($this->returnValue($categoryId));
        $product->expects($this->any())->method('getRequestPath')->will($this->returnValue($requestPathProduct));
        $product->expects($this->any())
            ->method('setRequestPath')
            ->with(false)
            ->will($this->returnSelf());
        $product->expects($this->any())->method('getId')->will($this->returnValue($productId));
        $product->expects($this->any())->method('getUrlKey')->will($this->returnValue($productUrlKey));
        $this->url->expects($this->any())->method('setScope')->with($storeId)->will($this->returnSelf());
        $this->url->expects($this->any())
            ->method('getUrl')
            ->with($routePath, $routeParamsUrl)
            ->will($this->returnValue($requestPathProduct));
        $this->urlFinder->expects($this->any())->method('findOneByData')->will($this->returnValue(false));

        switch ($getUrlMethod) {
            case 'getUrl':
                $this->assertEquals($requestPathProduct, $this->model->getUrl($product, $routeParams));
                break;
            case 'getUrlInStore':
                $this->assertEquals($requestPathProduct, $this->model->getUrlInStore($product, $routeParams));
                break;
            case 'getProductUrl':
                $this->assertEquals($requestPathProduct, $this->model->getProductUrl($product, null));
                $this->sidResolver
                    ->expects($this->never())
                    ->method('getUseSessionInUrl')
                    ->will($this->returnValue(true));
                break;
        }
    }

    /**
     * @return array
     */
    public function getUrlDataProvider()
    {
        return [
            [
                'getUrl',
                '',
                '/product/url/path',
                1,
                1,
                ['_scope' => 1],
                ['_scope' => 1, '_direct' => '/product/url/path', '_query' => []],
                null,
                null,
            ], [
                'getUrl',
                'catalog/product/view',
                false,
                1,
                1,
                ['_scope' => 1],
                ['_scope' => 1, '_query' => [], 'id' => 1, 's' => 'urlKey', 'category' => 1],
                1,
                'urlKey',
            ], [
                'getUrlInStore',
                '',
                '/product/url/path',
                1,
                1,
                ['_scope' => 1],
                ['_scope' => 1, '_direct' => '/product/url/path', '_query' => [], '_scope_to_url' => true],
                null,
                null,
            ], [
                'getProductUrl',
                '',
                '/product/url/path',
                1,
                1,
                [],
                ['_direct' => '/product/url/path', '_query' => [], '_nosid' => true],
                null,
                null,
            ]
        ];
    }
}
