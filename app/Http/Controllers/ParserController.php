<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Lib\Site\S01;
use Lib\Classes\Parser;

class ParserController extends Controller
{

	public function __construct ()
	{
		$this->parser = new Parser();
	}

    public function GetAllPrices ()
    {
    	return $this->parser->GetAllPrices();
    }

    public function GetAveragePrices ()
    {
    	return $this->parser->GetAveragePrices();
    }

    public function GetAllSites ()
    {
        return $this->parser->GetAllSiteTables();
    }
}
