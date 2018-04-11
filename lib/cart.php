<?php
    namespace Octo;

    class Cart
    {
        const CARTSUFFIX = '_cart';
        protected $session;
        protected $collection;
        protected $name = "Octocart";

        /**
         * @param null $name
         * @throws Exception
         * @throws \ReflectionException
         */
        public function __construct($name = null)
        {
            $name = is_null($name) ? def('SITE_NAME', 'site') : $name;

            $this->session = flew('cart.' . $name);

            $this->collection = lib('items');

            $this->setCart($name);
        }

        /**
         * @param $name
         * @throws Exception
         */
        public function setCart($name)
        {
            if (empty($name)) {
                throw new Exception('Cart name can not be empty.');
            }

            $this->name = $name . self::CARTSUFFIX;
        }

        public function getCart()
        {
            return $this->name;
        }

        /**
         * @param $name
         * @return $this
         * @throws Exception
         */
        public function named($name)
        {
            $this->setCart($name);

            return $this;
        }

        /**
         * @param array $product
         * @return mixed
         */
        public function add(array $product)
        {
            $this->collection->validateItem($product);

            if ($this->has($product['id'])) {
                $item = $this->get($product['id']);

                return $this->updateQty(
                    $item->id,
                    $item->quantity + $product['quantity']
                );
            }

            $this->collection->setItems(
                $this->session->get(
                    $this->getCart(),
                    []
                )
            );

            $items = $this->collection->insert($product);

            $this->session->set(
                $this->getCart(),
                $items
            );

            return $this->collection->make($items);
        }

        /**
         * @param array $product
         * @return mixed
         * @throws Exception
         */
        public function update(array $product)
        {
            $this->collection->setItems(
                $this->session->get(
                    $this->getCart(),
                    []
                )
            );

            if (! isset($product['id'])) {
                throw new Exception('id is required');
            }

            if (! $this->has($product['id'])) {
                throw new Exception('There is no item in shopping cart with id: ' . $product['id']);
            }

            $item = array_merge(
                (array) $this->get($product['id']),
                $product
            );

            $items = $this->collection->insert($item);

            $this->session->set(
                $this->getCart(),
                $items
            );

            return $this->collection->make($items);
        }

        /**
         * @param $id
         * @param int $quantity
         * @return mixed
         * @throws Exception
         */
        public function updateQty($id, int $quantity)
        {
            $item = (array) $this->get($id);

            $item['quantity'] = $quantity;

            return $this->update($item);
        }

        public function updatePrice($id, $price)
        {
            $item = (array) $this->get($id);

            $item['price'] = $price;

            return $this->update($item);
        }

        public function remove($id)
        {
            $items = $this->session->get(
                $this->getCart(),
                []
            );

            unset($items[$id]);

            $this->session->set(
                $this->getCart(),
                $items
            );

            return $this->collection->make($items);
        }

        public function items()
        {
            return $this->getItems();
        }

        public function getItems()
        {
            return $this->collection->make(
                $this->session->get(
                    $this->getCart()
                )
            );
        }

        public function get($id)
        {
            $this->collection->setItems(
                $this->session->get(
                    $this->getCart(),
                    []
                )
            );

            return $this->collection->findItem($id);
        }

        public function has($id)
        {
            $this->collection->setItems(
                $this->session->get(
                    $this->getCart(),
                    []
                )
            );

            return $this->collection->findItem($id) ? true : false;
        }

        public function count()
        {
            $items = $this->getItems();

            return $items->count();
        }

        public function getTotal()
        {
            $items = $this->getItems();

            return $items->sum(function($item) {
                return $item->price * $item->quantity;
            });
        }

        public function totalQuantity()
        {
            $items = $this->getItems();

            return $items->sum('quantity');
        }

        public function copy($cart)
        {
            if (is_object($cart)) {
                if (!$cart instanceof \Octo\Cart) {
                    throw new Exception("Argument must be an instance of " . get_class($this));
                }

                $items = $this->session->get(
                    $cart->getCart(),
                    []
                );
            } else {
                if (!$this->session->has($cart . self::CARTSUFFIX)) {
                    throw new Exception('Cart does not exist: ' . $cart);
                }

                $items = $this->session->get(
                    $cart . self::CARTSUFFIX,
                    []
                );
            }

            $this->session->set(
                $this->getCart(),
                $items
            );
        }

        public function flash()
        {
            $this->clear();
        }

        public function clear()
        {
            $this->session->remove($this->getCart());
        }
    }

