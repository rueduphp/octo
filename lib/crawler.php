<?php
    namespace Octo;

    class Crawler
    {
        public static function az($id)
        {
            $url        = "http://www.cuisineaz.com/recettes/$id-impression.aspx";
            $html       = dwnCache($url);
            $segment    = cut('<article class="recipe_main">', '</article>', $html);
            $data       = [];

            $data['title'] = cut('<h1>', '</h1>', $segment);
            $data['image'] = cut('src="', '"', $segment);
            $data['teaser'] = cut('<p>', '</p>', $segment);

            $ingredients = cut('<h3 class="titleBackGrey mb0">Ingrédients</h3>', '</ul>', $segment);
            $ingredients = str_replace(["\t", "\r", "\n", '<li ><span>', '</span></li>'], ['', '', '', '- ', '##br##'], $ingredients);

            $data['ingredient'] = '<p>' . str_replace('##br##', '<br>', strip_tags($ingredients)) . '</p>';

            $preparation = cut('<h3 class="titleBackGrey mb5">Préparation</h3>', '</div>', $segment);
            $preparation = str_replace(["\t", "\r", "\n", '<span class="dblock bold txt-dark-gray">', '</p>', '</span>'], ['', '', '', '##u####b##', '##br####br##', '##/b####/u####br##'], $preparation);

            $data['preparation'] = '<p>' .
            str_replace(
                ['##br##', '##u##', '##/u##', '##b##', '##/b##'],
                ['<br>', '<u>', '</u>', '<b>', '</b>'],
                strip_tags($preparation)
            ) .
            '</p>';

            $data['image'] = str_replace('/240x192', '', $data['image']);

            return $data;
        }
    }
