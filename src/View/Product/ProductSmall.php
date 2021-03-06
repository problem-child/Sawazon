<?php

namespace View\Product;

use Model\Product;
use Processing\Text\DefaultTextFilter;
use Processing\Text\TagsEmphasis;
use Routing\Route;
use View\Template;

class ProductSmall extends Template
{
    public function __construct($product_id)
    {
        parent::__construct('product/for_index');
        $product = (new Product())->load($product_id);
        $author = $product->user;
        $img = Route::get('image')->generate(['content' => 'product', 'id' => $product->product_id]);

        $user_link = Route::get('user_show')->generate(['id'=>$author->user_id]);
        $this->addParam('user_link', $user_link);


        $description = $product->description;
        $description = DefaultTextFilter::getInstance()->apply($description);
        $content = (new TagsEmphasis())->apply($description);


        $this->addParam('username', $author->username);
        $this->addParam('user-img', $img);
        $this->addParam('date', $product->published_on);
        $this->addParam('heading', $product->name);
        $this->addParam('content', $content);
        $this->addParam('price', getPrice($product->getLastPrice()));

        $plink = Route::get('product_show')->generate(['id' => $product->product_id]);

        $this->addParam('pname', $product->name);
        $this->addParam('plink', $plink);
    }
}