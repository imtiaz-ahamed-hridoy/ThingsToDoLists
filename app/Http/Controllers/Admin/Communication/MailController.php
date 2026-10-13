<?php

namespace App\Http\Controllers\Admin\Communication;


use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;

class MailController extends Controller
{

    public function index(Request $request)
    {

        if ($request->ajax()) {
            $data = EmailTemplate::all();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('body', function ($data) {
                    return $data->body ?? 'N/A';
                })

                // action buttons
                ->addColumn('action', function ($data) {
                    $buttons = '<div class="btn-group btn-group-sm" role="group" aria-label="Offer Actions">';
                    $buttons .= '<a href="' . route('mail.edit', $data->id) . '" class="btn btn-primary text-white" title="Edit"><i class="fe fe-edit"></i></a>';
                    $buttons .= '<button type="button" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger text-white" title="Delete"><i class="fe fe-trash"></i></button>';
                    $buttons .= '</div>';
                    return $buttons;
                })

                ->rawColumns(['action', 'body'])
                ->make(true);
        }

        return view('backend.layouts.sendMail.index');
    }
    
    public function create(){
        return view('backend.layouts.sendMail.create');
    }

    public function store(Request $request){
        $validated = $request->validate([
            'name' => 'required|string',
            'subject'=> 'required|string',
            'body'=> 'required|string',
            ]);

        $data = EmailTemplate::create([
            'name'=> $validated['name'],
            'subject'=> $validated['subject'],
            'body'=> $validated['body'],
        ]);

        return redirect()->route('mail.index')->with('t-success','Mail Template Created Successfully');
    }

    public function edit($id){
        $data = EmailTemplate::findOrFail($id);
        return view('backend.layouts.sendMail.edit', compact('data'));
    }

    public function update(Request $request, $id){
        $validated = $request->validate([
            'name' => 'required|string',
            'subject'=> 'required|string',
            'body'=> 'required|string',
        ]);

        $data = EmailTemplate::findOrFail($id);
        $data->name = $validated['name'];
        $data->subject= $validated['subject'];
        $data->body = $validated['body'];
        $data->save();

        return redirect()->route('mail.index')->with('t-success','Tempalte updated successfully');
    }

    public function destroy($id){
        $data = EmailTemplate::findOrFail($id);
        $data->delete();
        return response([
            't-success'=> true,
            'message'=> 'Data Deleted Successfully'
        ]);
    }


}

