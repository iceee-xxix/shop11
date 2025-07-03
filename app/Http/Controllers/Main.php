<?php

namespace App\Http\Controllers;

use App\Events\OrderCreated;
use App\Http\Controllers\admin\Category;
use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\Menu;
use App\Models\Orders;
use App\Models\OrdersDetails;
use App\Models\Promotion;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class Main extends Controller
{
    public function index(Request $request)
    {
        $table_id = $request->input('table');
        if ($table_id) {
            $table = Table::where('table_number', $table_id)->first();
            session(['table_id' => $table->id]);
        }
        $promotion = Promotion::where('is_status', 1)->get();
        $category = Categories::has('menu')->with('files')->get();
        return view('users.main_page', compact('category', 'promotion'));
    }

    public function detail($id)
    {
        $menu = Menu::where('categories_id', $id)->with('files', 'option')->orderBy('created_at', 'asc')->get();
        return view('users.detail_page', compact('menu'));
    }

    public function order()
    {
        return view('users.list_page');
    }

    public function SendOrder(Request $request)
    {
        $data = [
            'status' => false,
            'message' => 'สั่งออเดอร์ไม่สำเร็จ',
        ];
        $orderData = $request->input('orderData');
        $remark = $request->input('remark');
        $item = array();
        $total = 0;
        foreach ($orderData as $order) {
            foreach ($order as $rs) {
                $item[] = [
                    'id' => $rs['id'],
                    'price' => $rs['price'],
                    'option' => $rs['option'],
                    'qty' => $rs['qty'],
                ];
                $total = $total + ($rs['price'] * $rs['qty']);
            }
        }

        if (!empty($item)) {
            $order = new Orders();
            $order->table_id = session('table_id') ?? '1';
            $order->total = $total;
            $order->remark = $remark;
            $order->status = 1;
            if ($order->save()) {
                foreach ($item as $rs) {
                    $orderdetail = new OrdersDetails();
                    $orderdetail->order_id = $order->id;
                    $orderdetail->menu_id = $rs['id'];
                    $orderdetail->option_id = $rs['option'];
                    $orderdetail->quantity = $rs['qty'];
                    $orderdetail->price = $rs['price'];
                    $orderdetail->save();
                }
            }
            event(new OrderCreated(['📦 มีออเดอร์ใหม่']));
            $data = [
                'status' => true,
                'message' => 'สั่งออเดอร์เรียบร้อยแล้ว',
            ];
        }
        return response()->json($data);
    }

    public function sendEmp()
    {
        $table = Table::find(session('table_id'));
        event(new OrderCreated(['ลูกค้าเรียกจากโต้ะที่ ' . $table->table_number]));
    }
}
