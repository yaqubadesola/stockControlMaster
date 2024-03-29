<?php namespace App\Http\Controllers;

use App\Customer;
use App\CustomerPayment;
use App\Http\Controllers\Traits\FileUploadTrait;
use App\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Image;
use \Auth;
use \Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;

class CustomerController extends Controller
{
    use FileUploadTrait;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $customers = Customer::where(['type'=>1])->latest()->get();
        return view('customer.index')->with('customer', $customers);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('customer.edit');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $this->validator($request->all())->validate();
        // store
        $customers = new Customer;
        $customers->saveCustomer($request->all());
        if (!empty(Input::get('payment'))) {
            $payment = new CustomerPayment;
            $payment->payment = $request->payment;
            $payment->customer_id = $customers->id;
            $payment->user_id = Auth::user()->id;
            $payment->save();
        }

        // process avatar
        $image = $request->file('avatar');
        if (isset($image)) {
            $customers->saveCustomerAvatar($image);
        }
        Session::flash('message', __('You have successfully added customer'));
        return Redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $customer = Customer::findOrFail($id);
        $total_sales = Sale::where('customer_id', $id)->count();
        $total_dues = Sale::where('customer_id', $id)->sum('dues');
        $total_customer_payment = CustomerPayment::where('customer_id', $id)->sum('payment');
        $saleReport_dues = Sale::where([['customer_id', $id],['dues', '>', 0.00], ['status', '=', 0]])->paginate(10);
        $saleReport_completed = Sale::where([['customer_id', $id],['status', '=', 1]])->where([['customer_id', $id],['dues', '>', 0.00]])->paginate(10);
        $customer_payments = CustomerPayment::where('customer_id', $id)->latest()->paginate(3);
        $sale_payments = DB::select("select * from sales INNER JOIN sale_payments ON sales.id = sale_payments.sale_id where customer_id =".$id." ORDER BY sale_payments.id DESC LIMIT 5");
        $all_recs = array('total_customer_payment'  => $total_customer_payment, 
                            'customer'              => $customer, 
                            'saleReport_completed'  => $saleReport_completed,  
                            'saleReport_dues'       => $saleReport_dues, 
                            'total_sales'           => $total_sales, 
                            'total_dues'            => $total_sales, 
                            'customer_payments'     => $customer_payments,
                            'sale_payments'         => $sale_payments);
      //dd($saleReport_completed);
       
        return view('customer.show', compact('total_customer_payment', 'customer', 'saleReport_completed', 'saleReport_dues', 'total_sales', 'total_dues', 'customer_payments', 'sale_payments'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $customers = Customer::find($id);
        return view('customer.edit')
            ->with('customer', $customers);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $this->validator($request->all())->validate();
        $customers = Customer::find($id);
        $customers->saveCustomer($request->all());
        // process avatar
        $image = $request->file('avatar');
        if (isset($image)) {
            if (file_exists($customers->avatar) && $customers->avatar != 'no-foto.png') {
                unlink($customers->avatar);
            }
            $customers->saveCustomerAvatar($image);
        }
        // redirect
        Session::flash('message', __('You have successfully updated customer'));
        return Redirect::to('customers');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        try {
            $customers = Customer::find($id);
            $customers->delete();
            // redirect
            Session::flash('message', __('You have successfully deleted customer'));
            return Redirect::to('customers');
        } catch (\Illuminate\Database\QueryException $e) {
            Session::flash('message', __('Integrity constraint violation: You Cannot delete a parent row'));
            Session::flash('alert-class', 'alert-danger');
            return Redirect::to('customers');
        }
    }

    protected function validator(Array $data)
    {
        return Validator::make($data, [
            'avatar'=>'mimes:jpeg,bmp,png|max:5120kb',
            'name'=>'required|max:185',
            'email'=>'max:100',
            'address'=>'max:185',
            'city'=>'max:185',
            'state'=>'max:185',
            'company_name'=>'max:50',
            'account'=>'max:50',
            'zip'=>'max:10',
            'phone_number'=>'max:20',
            'prev_balance'=>'max:999999|numeric',
            'payment'=>'max:999999|numeric|nullable'
        ]);
    }
}
