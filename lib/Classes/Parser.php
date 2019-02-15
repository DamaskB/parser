<?
namespace Lib\Classes;

use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class Parser
{
	var $tname_pb = 'bs_progress_bar';

	var $tname_ap = 'bs_average_prices';

	public function __construct ()
	{
		$this->psr = new Document();

		if (get_called_class() != 'Lib\Classes\Parser' AND !Schema::hasTable('bs_site_' . $this->class_name))
			$this->Activate();

		if (!Schema::hasTable($this->tname_pb)) {
			Schema::create($this->tname_pb, function (Blueprint $table) {
				$table->increments('id');
				$table->string('site');
				$table->string('type');
				$table->float('percent');
			});
		}

		if (!Schema::hasTable($this->tname_ap)) {
			Schema::create($this->tname_ap, function (Blueprint $table) {
				$table->increments('id');
				$table->string('sku');
				$table->float('price');
			});
		}
	}

	public function InsertProduct ($data)
	{
		$exist = DB::table('bs_site_' . $this->class_name)
			->select('id', 'price')
			->where('sku', '=', $data['sku'])
			->limit(1)
			->first();

		$data['path'] = json_encode($data['path']);
		$data['allprice'] = json_encode($data['allprice']);

		if (@$exist->id) {
			if ($exist->price != $data['price'])
				DB::table('bs_site_' . $this->class_name)
					->where('id', $exist->id)
					->update($data);
		}
		else {
			DB::table('bs_site_' . $this->class_name)
				->insert($data);
		}
	}

	public function Activate ()
	{
		Schema::create('bs_site_' . $this->class_name, function (Blueprint $table) {
			$table->increments('id');
			$table->string('sku');
			$table->string('name');
			$table->float('price');
			$table->string('url');
			$table->string('minpart');
			$table->text('allprice')->nullable();
			$table->text('path');
		});
	}

	private function SetProgress ($type, $percent)
	{
		DB::table($this->tname_pb)
			->where('site', $this->class_name)
			->where('type', $type)
			->update([
				'percent' => $percent
			]);
	}

	public function GetProgress ()
	{
		return DB::table($this->tname_pb)->get()->toArray();
	}


	public function ClearProgress ()
	{
		$exist = DB::table($this->tname_pb)
			->select('id')
			->where('site', $this->class_name)
			->where('type', 'catalog')
			->limit(1)
			->first();

		if (@$exist->id) {
			DB::table($this->tname_pb)
				->where('id', $exist->id)
				->update(['percent' => 0]);
		}
		else {
			DB::table('bs_site_' . $this->class_name)
				->insert([
					'site' => $this->class_name,
					'type' => 'catalog',
					'percent' => 0
				]);
		}

		$exist = DB::table($this->tname_pb)
			->select('id')
			->where('site', $this->class_name)
			->where('type', 'items')
			->limit(1)
			->first();

		if (@$exist->id) {
			DB::table($this->tname_pb)
				->where('id', $exist->id)
				->update(['percent' => 0]);
		}
		else {
			DB::table('bs_site_' . $this->class_name)
				->insert([
					'site' => $this->class_name,
					'type' => 'items',
					'percent' => 0
				]);
		}
	}

	public function Deactivate ()
	{
		Schema::dropIfExists('bs_site_' . $this->class_name);
	}

	public function Parse ()
	{
		set_time_limit(0);

		$start = microtime(true);

		$this->GetCatalogList();

		$this->ClearProgress();

		if (@$this->catalog) {
			$this->catalog = array_slice($this->catalog, 0, 5);
			$i = 1;
			$count['cat'] = count($this->catalog);
			$count['cat_per'] = (int) ($count['cat'] / 100);
			if ($count['cat_per'] < 2) $count['cat_per'] = 2;

			foreach ($this->catalog as $cat_id) {
				$this->GetProductsFromCatalog('/catalog/' . $cat_id . '/');
				if ($i % $count['cat_per'] == 0 OR ($i / $count['cat'] * 100) == 100)
					$this->SetProgress('catalog', $i / $count['cat'] * 100 );
				$i++;
			}
		}
		
		unset($this->catalog);

		if (@$this->products) {
			$i = 1;

			$count['item'] = count($this->products);
			$count['item_per'] = (int) ($count['item'] / 100);
			if ($count['item_per'] < 2) $count['item_per'] = 2;

			foreach ($this->products as $item) {
				$this->InsertProduct($item);
				if ($i % $count['item_per'] == 0 OR ($i / $count['item'] * 100) == 100)
					$this->SetProgress('items', $i / $count['item'] * 100 );
				$i++;
			}
		}

		$this->CalcAveragePrice();

		$end = microtime(true) - $start;

		dump('Time: ' . number_format($end, 2) . ' s.');

		dump('Mem.: ' . (round(memory_get_usage()/1048576,2)) . ' MB');

		dump('MaxM: ' . (round(memory_get_peak_usage()/1048576,2)) . ' MB');

		dump($count);

		return 'xxx';
	}

	public function GetAllSiteTables ()
	{
		$list = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = :table AND table_name LIKE "bs_site_%";', ['table' => env('DB_DATABASE')]);

		foreach ($list as $v) {
			$new_list[] = $v->TABLE_NAME;
		}

		return $this->all_site_tables = @$new_list;
	}

	public function GetPricesFromTable ($table)
	{
		$raw = DB::table($table)
			->select('sku', 'price')
			->get()
			->toArray();

		if (@$raw) {
			foreach ($raw as $item) {
				$this->site_prices[$item->sku][$table] = $item->price;
			}
		}
	}

	public function GetAllPrices ()
	{
		$this->GetAllSiteTables();

		if (@$this->all_site_tables) {
			foreach ($this->all_site_tables as $table) {
				$this->GetPricesFromTable($table);
			}
		}

		return @$this->site_prices;
	}

	public function CalcAveragePrice ()
	{
		$this->GetAllPrices();

		if (@$this->site_prices) {
			foreach ($this->site_prices as $sku => $dbs) {
				foreach ($dbs as $db => $price) {
					@$raw_total[$sku]['count']++;
					@$raw_total[$sku]['total'] += $price;
				}
			}

			foreach ($raw_total as $sku => $v) {
				$this->calc_average_prices[] = [
					'sku' => $sku,
					'price' => number_format($v['total'] / $v['count'], 2, '.', '')
				];
			}
		}

		$this->SaveAveragePrice();
	}

	private function SaveAveragePrice ()
	{
		DB::table($this->tname_ap)->truncate();

		DB::table($this->tname_ap)->insert($this->calc_average_prices);
	}

	public function GetAveragePrices ()
	{
		$raw = DB::table($this->tname_ap)->get();

		if ($raw) {
			foreach ($raw as $v) {
				$this->site_average_prices[$v->sku] = $v->price;
			}
		}

		return @$this->site_average_prices;
	}
}