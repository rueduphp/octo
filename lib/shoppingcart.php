<?php

namespace Octo;

class EventCart extends Fire {}

class Shoppingcart
{
    /**
     * @var Ultimate
     */
    protected $session;

    /**
     * @var EventCart
     */
    protected $event;

    /**
     * @var string
     */
    protected $instance;

    /**
     * @var string
     */
    protected $associatedModel;

    /**
     * @var string
     */
    protected $associatedModelNamespace;

    /**
     * @param null $session
     * @param null $event
     * @throws Exception
     * @throws \ReflectionException
     */
    public function __construct($session = null, $event = null)
    {
        $this->session  = $session ?? getSession();
        $this->event    = $event ?? gi()->make(EventCart::class);

        $this->instance = 'main';
    }

    /**
     * @param string $instance
     * @return Shoppingcart
     */
    public function instance(string $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    /**
     * @param string $modelName
     * @param null|string $modelNamespace
     * @return Shoppingcart
     */
    public function associate(string $modelName, ?string $modelNamespace = null): self
    {
        $this->associatedModel = $modelName;
        $this->associatedModelNamespace = $modelNamespace;

        return $this;
    }

    /**
     * @param $id
     * @param null|string $name
     * @param int|null $qty
     * @param float|null $price
     * @param float|null $discount
     * @param array $options
     * @return null|Shoppingcart
     */
    public function add(
        $id,
        ?string $name = null,
        ?int $qty = null,
        ?float $price = null,
        ?float $discount = null,
        array $options = []
    ) {

        if (is_array($id)) {
            if ($this->is_multi($id)) {
                $this->event->fire('cart.batch', $id);

                foreach ($id as $item) {
                    $options = aget($item, 'options', []);

                    $this->addRow(
                        $item['id'],
                        $item['name'],
                        $item['qty'],
                        $item['price'],
                        $this->discountResolve($item),
                        $options
                    );
                }

                $this->event->fire('cart.batched', $id);

                return null;
            }

            $options = aget($id, 'options', []);

            $this->event->fire('cart.add', array_merge($id, ['options' => $options]));

            $result = $this->addRow(
                $id['id'],
                $id['name'],
                $id['qty'],
                $id['price'],
                $this->discountResolve($id),
                $options
            );

            $this->event->fire('cart.added', array_merge($id, ['options' => $options]));

            return $result;
        }

        $this->event->fire('cart.add', compact('id', 'name', 'qty', 'price', 'options'));

        $result = $this->addRow($id, $name, $qty, $price, $discount, $options);

        $this->event->fire('cart.added', compact('id', 'name', 'qty', 'price', 'options'));

        return $result;
    }

    /**
     * @param $data
     * @return float|null
     */
    public function discountResolve($data)
    {
        if (isset($data['discount'])) {
            return floatval($data['discount']);
        }

        return null;
    }

    /**
     * @param $rowId
     * @param $attribute
     * @return bool|ShoppingCartCollection
     * @throws Exception
     */
    public function update($rowId, $attribute)
    {
        if (!$this->hasRowId($rowId)) throw new Exception($rowId . ' does not exist');

        if (is_array($attribute)) {
            $this->event->fire('cart.update', $rowId);

            $result = $this->updateAttribute($rowId, $attribute);

            $this->event->fire('cart.updated', $rowId);

            return $result;
        }

        $this->event->fire('cart.update', $rowId);

        $result = $this->updateQty($rowId, $attribute);

        $this->event->fire('cart.updated', $rowId);

        return $result;
    }

    /**
     * @param string $rowId
     * @return bool
     * @throws Exception
     */
    public function remove(string $rowId)
    {
        if (!$this->hasRowId($rowId)) throw new Exception($rowId . ' does not exist');

        $cart = $this->getContent();

        $this->event->fire('cart.remove', $rowId);

        $cart->forget($rowId);

        $this->event->fire('cart.removed', $rowId);

        return $this->updateCart($cart);
    }

    /**
     * @param string $rowId
     * @return mixed|null
     */
    public function get(string $rowId)
    {
        $cart = $this->getContent();

        return ($cart->has($rowId)) ? $cart->get($rowId) : null;
    }

    /**
     * @return null|ShoppingCartCollection
     */
    public function content()
    {
        $cart = $this->getContent();

        return (empty($cart)) ? null : $cart;
    }

    /**
     * @return bool
     */
    public function destroy()
    {
        $this->event->fire('cart.destroy');

        $result = $this->updateCart(null);

        $this->event->fire('cart.destroyed');

        return $result;
    }


    /**
     * @param bool $totalItems
     * @return int
     */
    public function count($totalItems = true)
    {
        $cart = $this->getContent();

        if (!$totalItems) {
            return $cart->count();
        }

        $count = 0;

        foreach ($cart as $row) {
            $count += $row->qty;
        }

        return $count;
    }

    /**
     * @param  array $search
     * @return array|bool
     */
    public function search(array $search)
    {
        if (empty($search)) return false;

        /** @var ShoppingRow $item */
        foreach ($this->getContent() as $item) {
            $found = $item->search($search);

            if ($found) {
                $rows[] = $item->rowid;
            }
        }

        return empty($rows) ? false : $rows;
    }

    /**
     * @param string $id
     * @param string $name
     * @param int $qty
     * @param float $price
     * @param float $discount
     * @param array $options
     * @return Shoppingcart
     */
    protected function addRow(
        string $id,
        string $name,
        int $qty,
        float $price,
        float $discount,
        array $options = []
    ): self {
        $cart = $this->getContent();
        $rowId = $this->generateRowId($id, $options);

        if ($cart->has($rowId)) {
            $row = $cart->get($rowId);
            $cart = $this->updateRow($rowId, ['qty' => $row->qty + $qty]);
        } else {
            $cart = $this->createRow($rowId, $id, $name, $qty, $price, $discount, $options);
        }

        $this->updateCart($cart);
        $this->setTotal();
        $this->setSubTotal();
        $this->setDiscount();

        return $this;
    }

    /**
     * @param $id
     * @param $options
     * @return string
     */
    protected function generateRowId($id, $options): string
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    /**
     * @param $rowId
     * @return bool
     */
    protected function hasRowId($rowId): bool
    {
        return $this->getContent()->has($rowId);
    }

    /**
     * @param $cart
     * @return bool
     */
    protected function updateCart($cart): bool
    {
        $this->session->set($this->getInstance(), $cart);

        return true;
    }

    /**
     * @return ShoppingCartCollection
     */
    protected function getContent(): ShoppingCartCollection
    {
        $content = $this->session->get($this->getInstance()) ?? new ShoppingCartCollection();

        return $content;
    }

    /**
     * @return string
     */
    protected function getInstance(): string
    {
        return 'cart.' . $this->instance;
    }

    /**
     * @param $rowId
     * @param $attributes
     * @return ShoppingCartCollection
     */
    protected function updateRow($rowId, $attributes): ShoppingCartCollection
    {
        $cart = $this->getContent();

        /** @var ShoppingRow $row */
        $row = $cart->get($rowId);

        foreach ($attributes as $key => $value) {
            if ($key === 'options') {
                $options = $row->options->merge($value);
                $row->put($key, $options);
            } else {
                $row->put($key, $value);
            }
        }

        if (!is_null(array_keys($attributes, ['qty', 'price']))) {
            $row->put('total', $row->qty * $row->price);
            $row->put('total_discount', $row->qty * $row->discount);
            $row->put('subtotal', ($row->qty * $row->price) - ($row->qty * $row->discount));
        }

        $cart->put($rowId, $row);

        $this->setTotal();
        $this->setSubTotal();
        $this->setDiscount();

        return $cart;
    }

    /**
     * @param string $rowId
     * @param string $id
     * @param string $name
     * @param int $qty
     * @param float $price
     * @param float $discount
     * @param array $options
     * @return ShoppingCartCollection
     */
    protected function createRow(
        string $rowId,
        string $id,
        string $name,
        int $qty,
        float $price,
        float $discount,
        array $options = []
    ): ShoppingCartCollection {
        $cart = $this->getContent();

        $newRow = new ShoppingRow([
            'rowid'             => $rowId,
            'id'                => $id,
            'name'              => $name,
            'qty'               => $qty,
            'price'             => $price,
            'discount'          => $discount,
            'options'           => new ShoppingOption($options),
            'total'             => $qty * $price,
            'total_discount'    => $qty * $discount,
            'subtotal'          => ($qty * $price) - ($qty * $discount),
        ], $this->associatedModel, $this->associatedModelNamespace);

        $cart->put($rowId, $newRow);

        return $cart;
    }

    /**
     * @param string $rowId
     * @param int $qty
     * @return bool|ShoppingCartCollection
     * @throws Exception
     */
    protected function updateQty(string $rowId, int $qty = 1)
    {
        if ($qty <= 0) {
            return $this->remove($rowId);
        }

        return $this->updateRow($rowId, ['qty' => $qty]);
    }

    /**
     * @param string $rowId
     * @param array $attributes
     * @return ShoppingCartCollection
     */
    protected function updateAttribute(string $rowId, array $attributes): ShoppingCartCollection
    {
        return $this->updateRow($rowId, $attributes);
    }

    /**
     * @param array $array
     * @return bool
     */
    protected function is_multi(array $array): bool
    {
        return is_array(reset($array));
    }

    /**
     * @param float $amount
     * @return bool
     */
    protected function setCustomDiscount(float $amount): bool
    {
        $cart = $this->getContent();

        if (!$cart->isEmpty()) {
            $cart->custom_discount = floatval($amount);
            $this->setSubTotal();
            $this->updateCart($cart);

            return true;
        }

        return false;
    }

    /**
     * @return float
     */
    public function customDiscount(): float
    {
        return $this->getContent()->custom_discount;
    }

    /**
     * @return bool
     */
    public function setDiscount(): bool
    {
        $cart = $this->getContent();

        if ($cart->isEmpty()) {
            return false;
        }

        $discount = 0;

        foreach ($cart as $row) {
            $discount += $row->total_discount;
        }

        $cart->discount = floatval($discount);
        $this->updateCart($cart);

        return true;
    }

    /**
     * @return float
     */
    public function discount(): float
    {
        return $this->getContent()->discount;
    }

    /**
     * @return bool
     */
    protected function setTotal()
    {
        $cart = $this->getContent();

        if ($cart->isEmpty()) {
            return false;
        }

        $total = 0;

        foreach ($cart as $row) {
            $total += $row->total;
        }

        $cart->total = floatval($total);
        $this->updateCart($cart);

        return true;
    }

    /**
     * @return float
     */
    public function total(): float
    {
        return $this->getContent()->total;
    }

    /**
     * @return bool
     */
    protected function setSubTotal()
    {
        $cart = $this->getContent();

        if ($cart->isEmpty()) {
            return false;
        }

        $subtotal = 0;

        foreach ($cart AS $row) {
            $subtotal += $row->subtotal;
        }

        $cart->subtotal = floatval($subtotal - $this->customDiscount());
        $this->updateCart($cart);

        return true;
    }

    /**
     * @return float
     */
    public function subtotal(): float
    {
        return $this->getContent()->subtotal;
    }
}

class ShoppingCartCollection extends Collection
{
    public $discount        = 0.00;
    public $custom_discount = 0.00;
    public $total           = 0.00;
    public $subtotal        = 0.00;
}

class ShoppingOption extends Collection
{
    public function __construct($items)
    {
        parent::__construct($items);
    }

    public function __get($arg)
    {
        if($this->has($arg)) {
            return $this->get($arg);
        }

        return null;
    }

    public function search($search, $strict = false)
    {
        foreach($search as $key => $value) {
            $found = ($this->{$key} === $value) ? true : false;

            if(!$found) return false;
        }

        return $found;
    }

}

class ShoppingRow extends Collection
{
    /**
     * @var string
     */
    protected $associatedModel;

    /**
     * @var string
     */
    protected $associatedModelNamespace;

    /**
     * @param array    $items
     * @param string   $associatedModel
     * @param string   $associatedModelNamespace
     */
    public function __construct($items, $associatedModel, $associatedModelNamespace)
    {
        parent::__construct($items);

        $this->associatedModel = $associatedModel;
        $this->associatedModelNamespace = $associatedModelNamespace;
    }

    public function __get($arg)
    {
        if ($this->has($arg)) {
            return $this->get($arg);
        }

        if ($arg === strtolower($this->associatedModel)) {
            $modelInstance = $this->associatedModelNamespace
                ? $this->associatedModelNamespace . '\\' .$this->associatedModel
                : $this->associatedModel
            ;

            $model = new $modelInstance;

            return $model->find($this->id);
        }

        return null;
    }

    /**
     * @param $search
     * @param bool $strict
     * @return bool|false|int|string
     */
    public function search($search, $strict = false)
    {
        foreach($search as $key => $value) {
            if($key === 'options') {
                $found = $this->{$key}->search($value);
            } else {
                $found = ($this->{$key} === $value) ? true : false;
            }

            if(!$found) return false;
        }

        return $found;
    }
}
