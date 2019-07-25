<?php namespace OFFLINE\MicroCart\Controllers;

use Backend\Behaviors\RelationController;
use Backend\Classes\Controller;
use BackendMenu;
use OFFLINE\MicroCart\Classes\Money;
use OFFLINE\MicroCart\Models\Cart;
use Backend\Behaviors\ListController;

class Carts extends Controller
{
    public $implement = [ListController::class, RelationController::class];

    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();
        $this->addCss('/plugins/offline/microcart/assets/backend.css');
    }

    public function listExtendQuery($query)
    {
        $query->completedOrders();
    }

    public function show()
    {
        $this->bodyClass = 'compact-container';
        $this->pageTitle = trans('offline.microcart::lang.titles.orders.show');
        $this->vars['indexUrl'] = \Backend::url('offline/microcart/carts');

        $cart = Cart::with('items')->findOrFail($this->params[0]);

        $this->initRelation($cart, 'payment_logs');

        $this->vars['cart'] = $cart;
        $this->vars['money'] = Money::instance();
    }
}
