<?php

namespace frontend\controllers;

use Yii;
// use yii\web\Controller;
use frontend\base\Controller;
use common\models\CartItem;
use common\models\Product;
use yii\web\Response;
use yii\filters\VerbFilter;
use common\models\OrderAddress;
use common\models\Order;


class CartController extends Controller {


    public function behaviors() {

        return [
            [

                // Posto metoda actionAdd() vraca array app.js funckciji a treba JSON, neophodno je:
                'class' => \yii\filters\ContentNegotiator::class,
                'only' => ['add'], // metoda actionAdd()
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            [
                // delete je moguc samo uz POST metod:
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST', 'DELETE'],
                ]
            ]
        ];

    }

    public function actionIndex() {

        if(Yii::$app->user->isGuest) {
            // get the items from session
            // ovo na kraju ", []", znaci da ako ne postoji daj prazan array
            $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
        } else {
            $cartItems = CartItem::getItemsForUser(currUserId());
        }

        return $this->render('index', [

            'items' => $cartItems

        ]);

    }

    public function actionAdd() {

        // id iz app.js, ili ti iz button-a 'add to cart' iz _product_item (ajax varijanta):
        $id = Yii::$app->request->post('id');
        $product = Product::find()->id($id)->published()->one();
        if(!$product) {
            throw new \yii\web\NotFoundHttpException("Product does not exist");
        }
        if(Yii::$app->user->isGuest) {
            // Save in session
            $cartItem=[
                'id' => $id,
                'name' => $product->name,
                'image' => $product->image,
                'price' => $product->price,
                'quantity' => 1,
                'total_price' => $product->price
            ];

            $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
            $id_ = array_column($cartItems, 'id');
            $found_key = array_search($id, $id_);
            // $found_key moze da bude tipa boolean i tipa integer. Napr. ako je tipa integer i vrednost je nula to znaci
            // da je nasao podatak u array index = 0 sto je ok. Ako nije nasao nista vraca podatak tipa booelan vrednosti
            // false:
            if(is_bool($found_key) === true && !$found_key) {
                array_push($cartItems, $cartItem);
            } else {
                $cartItems[$found_key]['quantity'] = $cartItems[$found_key]['quantity'] + 1;
            }
            Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
        } else {

            $userId = Yii::$app->user->id;
            $cartItem = CartItem::find()->userId($userId)->productId($id)->one();

            if($cartItem) {
                $cartItem->quantity++;
            } else {
                $cartItem = new CartItem();
                $cartItem->product_id = $id;
                $cartItem->created_by = Yii::$app->user->id;
                $cartItem->quantity = 1;
            }

            if($cartItem->save()) {
                return [
                    'success' => true
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => $cartItem->errors
                ];
            }
        }

    }

    public function actionDelete($id) {

        if(isGuest()) {
            $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
            foreach($cartItems as $i => $cartItem) {
                if($cartItem['id'] == $id) {
                    array_splice($cartItems, $i, 1);
                    break;
                }
            }
            Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
        } else {
            CartItem::deleteAll(['product_id' => $id, 'created_by' => currUserId()]);
        }

        return $this->redirect(['index']);
    }

    public function actionChangeQuantity() {

        $id = Yii::$app->request->post('id');
        $product = Product::find()->id($id)->published()->one();
        if(!$product) {
            throw new \yii\web\NotFoundHttpException("Product does not exist");
        }

        $quantity = Yii::$app->request->post('quantity');
        
        if($quantity >= 1) {
            if(isGuest()) {
                $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
                foreach($cartItems as &$cartItem) {
                  if($cartItem['id'] === $id) {
                    $cartItem['quantity'] = $quantity;
                    break;
                  }
                }
                Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
            } else {
                $cartItem = CartItem::find()->userId(currUserId())->productId($id)->one();
                if($cartItem) {
                  $cartItem->quantity = $quantity;
                  $cartItem->save();
                }
            }
            
        }                
        
        return CartItem::getTotalQuantityForUser(currUserId());
        
    }
    
    public function actionCheckout() {
        
        
        $order = new Order();
        $orderAddress = new OrderAddress();
        
        if(!isGuest()) {
            
            /** @var \common\models\User $user */
            $user = Yii::$app->user->identity;
            $userAddress = $user->getAddress();
            
            $order->firstname = $user->firstname;
            $order->lastname = $user->lastname;
            $order->email = $user->email;
            $order->status = Order::STATUS_DRAFT;
            
            
            $orderAddress->address = $userAddress->address;
            $orderAddress->city = $userAddress->city;
            $orderAddress->state = $userAddress->state;
            $orderAddress->country = $userAddress->country;
            $orderAddress->zipcode = $userAddress->zipcode;
            $cartItems = CartItem::getItemsForUser(currUserId());
        } else {
            $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
        }
        
        $productQuantity = CartItem::getTotalQuantityForUser(currUserId());
        $totalPrice = CartItem::getTotalPriceForUser(currUserId());
        
        return $this->render('checkout', [
            'order' => $order,
            'orderAddress' => $orderAddress,
            'cartItems' => $cartItems,
            'productQuantity' => $productQuantity,
            'totalPrice' => $totalPrice    
        ]);
        
        
    }

}