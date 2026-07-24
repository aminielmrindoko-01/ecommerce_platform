<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('products.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('categories'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('deals'), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => route('vendors'), 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => route('about'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => route('contact'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => route('blog'), 'priority' => '0.6', 'changefreq' => 'weekly'],
        ];

        foreach (Category::orderBy('sort_order')->get() as $category) {
            $urls[] = [
                'loc' => route('products.index', ['category' => $category->slug]),
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ];
        }

        foreach (Product::latest()->take(500)->get() as $product) {
            $urls[] = [
                'loc' => route('products.show', $product->id),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => optional($product->updated_at)->toAtomString(),
            ];
        }

        $xml = view('seo.sitemap', compact('urls'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $sitemap = url('/sitemap.xml');
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /account\nDisallow: /checkout\nDisallow: /cart\n\nSitemap: {$sitemap}\n";

        return response($body, 200)->header('Content-Type', 'text/plain');
    }
}
