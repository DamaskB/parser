<?
namespace Lib\Site;

use Lib\Classes\Parser;

Class S01 extends Parser
{
	var $class_name = 's01';

	var $site_url = 'https://xn----7sbabh4cwadrb5e.xn--p1ai';

	public function GetProduct ($html, $breadcrumbs = NULL)
	{
		$max_price = trim($html->first('.price::text'));

		$all_price[1] = $max_price;

		$raw_prices = $html->find('.specialPriceBlock tr');

		if ($raw_prices) {
			foreach ($raw_prices as $val) {
				$all_price[(int) $val->attr('data-count')] = (float) $val->first('td:nth-child(2)::text');
			}
		}

		$this->products[] = [
			'sku' => trim($html->first('.article::text')),
			'name' => $html->first('.title::text'),
			'price' => $max_price,
			'url' => $html->first('.title::attr(href)'),
			'path' => $breadcrumbs,
			'minpart' => (int) substr(trim($html->first('.party::text')), 22),
			'allprice' => $all_price
		];
	}

	public function GetProductsFromCatalog ($url)
	{
		$html = @$this->psr->loadHtmlFile($this->site_url . $url . '?count=all');

		$items = @$html->find('.itembg');

		if (@$items) {
			$rawpath = $html->find('#breadcrumb a[itemprop="url"]');

			foreach ($rawpath as $v) {
				$path[] = [
					'url' => $v->attr('href'),
					'title' => $v->attr('title')
				];
			}

			$path[] = [
				'url' => $url,
				'title' => trim($html->first('h1::text'))
			];

			foreach ($items as $item) {
				$this->GetProduct($item, $path);
			}
		}
	}

	public function GetCatalogList ()
	{
		$file_name = 'temp/sitemap_' . date('Y_m_d') . '_' . $this->class_name;

		if (!file_exists($file_name)) {

			$xml = $this->psr->loadXmlFile($this->site_url.'/sitemap.xml');
			file_put_contents($file_name, $xml);

		}
		else {
			$xml = $this->psr->loadXmlFile($file_name);
		}

		preg_match_all('#<loc>(.+?)</loc>#su', $xml->xml(), $catalog_raw);
		
		foreach ($catalog_raw[1] as $v) {
			$v = explode('/', str_replace($this->site_url, '', $v));
			if ($v[1] == 'catalog' && $v[2] != NULL)
				$catalog[] = $v[2];
		}

		$this->catalog = array_unique($catalog);
	}
}