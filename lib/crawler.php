<?php
    namespace Octo;

    class Crawler
    {
        public static function marmiton($id)
        {
            $url    = "http://www.marmiton.org/recettes/recette_recette_$id.aspx";
            $html   = dwnCache($url);
            $data   = [];

            $data['image'] = cut('<meta property="og:image" content="', '"', $html);
            $data['title'] = cut('<span class="fn">', '</span', $html);

            $segment = cut('<div class="m_content_recette_ingredients m_avec_substitution" data-content="switch-conversion">', '<div class="m_content_recette_todo">', $html);
            $data['ingredient'] = strip_tags(
                str_replace(
                    '<br/>',
                    '##br##',
                    cut(
                        '</span>',
                        '</div>',
                        $segment
                    )
                )
            );

            $segment = cut('<h4>Préparation de la recette :</h4>', '<div style="clear: both;"></div>', $html);
            $data['preparation'] = strip_tags(
                str_replace(
                    '<br/>',
                    '##br##',
                    cut(
                        '<br />',
                        '<div class="m_content_recette_ps">',
                        $segment
                    )
                )
            );
        }
    }
