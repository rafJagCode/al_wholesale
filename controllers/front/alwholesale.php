<?php

class al_wholesaleAlwholesaleModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');
        http_response_code(200);

        $payload = json_decode(file_get_contents('php://input'));

        $action = $payload->action;

        if($action === 'GET_CATEGORIES'){
            $categories = $this->getCategories();
            echo json_encode($categories);
            return;
        }

        if($action === 'GET_COLLECTIONS'){
            $collections = $this->getCollections();
            echo json_encode($collections);
            return;
        }

        if($action === 'GET_PRODUCTS'){
            $page = $payload->page;
            $limit = $payload->limit;
            $id_categories = $payload->id_categories;
            $phrase = $payload->phrase;

            $products = $this->getProducts(1, $page, $limit, 'price', 'DESC', $id_categories, true, $phrase);
            echo json_encode($products);
            return;
        }

        http_response_code(404);
        echo json_encode('404 Not Found');
    }

    public function getCategories()
    {
        return Db::getInstance()->ExecuteS('
		SELECT c.`id_category`, cl.`name`
		FROM `'._DB_PREFIX_.'category` c
		LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON c.`id_category` = cl.`id_category`
		WHERE `id_lang` = 1
		AND c.`id_parent` = 12
		AND `active` = 1');
    }

    public function getCollections()
    {
        return Db::getInstance()->ExecuteS('
		SELECT c.`id_category`, cl.`name`
		FROM `'._DB_PREFIX_.'category` c
		LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON c.`id_category` = cl.`id_category`
		WHERE `id_lang` = 1
		AND c.`id_parent` = 28
		AND `active` = 1');
    }

    public function getProducts($id_lang, $start, $limit, $orderBy, $orderWay, $id_categories = null, $only_active = false, $phrase = null)
    {
        //ORDER BY PREFIXES
        if (!Validate::isOrderBy($orderBy) OR !Validate::isOrderWay($orderWay))
            die (Tools::displayError());
        if ($orderBy == 'id_product' OR	$orderBy == 'price' OR	$orderBy == 'date_add')
            $orderByPrefix = 'p';
        elseif ($orderBy == 'name')
            $orderByPrefix = 'pl';
        elseif ($orderBy == 'position')
            $orderByPrefix = 'c';

        //BASIC SQL
        $sql = new DbQuery();
        $sql->from('product', 'p');
        $sql->leftJoin('product_lang', 'pl', 'p.`id_product` = pl.`id_product`');
        if(!empty($id_categories)){
            $sql->leftJoin('category_product', 'c', 'c.`id_product` = p.`id_product`');
        }
        $sql->where('pl.`id_lang` = '.intval($id_lang));
        if(!empty($id_categories)){
            $sql->where('c.`id_category` IN ('.implode(',',$id_categories).')');
        }
        if($only_active){
            $sql->where('p.`active` = 1');
        }
        if(isset($phrase)){
            $sql->where('pl.`name` LIKE \'%'.$phrase.'%\' OR p.`reference` LIKE \'%'.$phrase.'%\'');
        }

        //MAIN SQL
        $mainSql = clone $sql;

        $mainSql->select(
            'p.`id_product`, p.`price`, p.`ean13`, p.`quantity`, p.`reference` AS sku, 
		     pl.`name`, pl.`link_rewrite`, pl.`description`');
        $mainSql->orderBy((isset($orderByPrefix) ? $orderByPrefix.'.' : '').'`'.$orderBy.'` '.$orderWay);
        if($limit > 0){
            $mainSql->limit(intval($limit), intval($start));
        }

        $rq = Db::getInstance()->ExecuteS($mainSql);

        if($orderBy == 'price')
            Tools::orderbyPrice($rq,$orderWay);


        //COUNTER SQL
        $counterSql = clone $sql;
        $counterSql->select('COUNT(*) AS total_count');

        $counter = Db::getInstance()->getRow($counterSql);



        //ADD IMAGES AND FEATURES
        $products = ($rq);
        foreach ($products as &$product){
            $product['image'] = $this->getProductCoverImageLink($product['id_product']);
            $product['features'] = $this->getProductFeatures($product['id_product']);
        }

        //ADD PAGINATION INFO
        $result['products'] = $products;
        $result['pagination']['total_count'] = $counter['total_count'];
        $result['pagination']['page'] = $start;
        $result['pagination']['limit'] = $limit;

        return $result;

    }

    public function getProductCoverImageLink($id_product){
        $image = Image::getCover($id_product);
        $product = new Product($id_product);
        $link = new Link;

        return $link->getImageLink($product->link_rewrite[Context::getContext()->language->id], $image['id_image'], 'home_default');
    }

    public function getProductFeatures($id_product){
        return Product::getFrontFeaturesStatic(1, $id_product);
    }
}