<?php

namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Http\Request;

class CardController extends Controller
{

    public function get(): \Illuminate\Database\Eloquent\Collection
    {
        $card = Card::all();
        echo "Hallo";
        return $card;
    }
    //
}
