<?php
class RankingController extends Controller
{

    private function sanitizeData($data)
    {
        if (is_array($data)) {
            // Parcurge fiecare element din array
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeData($value);
                } elseif (is_string($value)) {
                    // Converteste caracterele speciale in entitati HTML
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
        } elseif (is_string($data)) {
            // Sanitizeaza direct string-urile
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    //Genereaza si returneaza clasamentele in format RSS sau JSON
    public function getRankings()
    {
        if (ob_get_level())
            ob_end_clean();

        // Sanitizare parametri din URL
        $type = isset($this->params['type']) ?
            htmlspecialchars($this->params['type'], ENT_QUOTES, 'UTF-8') :
            (isset($_GET['type']) ? htmlspecialchars($_GET['type'], ENT_QUOTES, 'UTF-8') : 'most_detestable');

        $format = isset($this->params['format']) ?
            htmlspecialchars($this->params['format'], ENT_QUOTES, 'UTF-8') :
            (isset($_GET['format']) ? htmlspecialchars($_GET['format'], ENT_QUOTES, 'UTF-8') : 'rss');

        $centralThreshold = 50.0;
        $ratingThreshold = 2.5;

        $validTypes = ['most_detestable', 'least_detestable'];
        $validFormats = ['rss', 'json'];

        if (!in_array($type, $validTypes)) {
            if ($format === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'error' => 'Invalid ranking type. Valid types are: ' . implode(', ', $validTypes)
                ]);
            } else {
                header('Content-Type: application/xml; charset=utf-8');
                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                echo '<error>Invalid ranking type. Valid types are: ' . implode(', ', $validTypes) . '</error>';
            }
            exit;
        }

        //verifica formatul
        if (!in_array($format, $validFormats)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Invalid format. Valid formats are: ' . implode(', ', $validFormats)
            ]);
            exit;
        }

        try {
            $rawEntities = $this->model->getAllEntities();

            // Calculeaza scorurile de detestabilitate
            $entities = $this->calculateEntityDesirability($rawEntities, $ratingThreshold);

            // Filtreaza si sorteaza entitatile
            $filteredEntities = $this->filterAndSortEntities($entities, $type, $centralThreshold);

            // Sanitizeaza datele inainte de afisare
            $filteredEntities = $this->sanitizeData($filteredEntities);


            if (empty($filteredEntities)) {
                $noResults = "Nu există entități care să îndeplinească criteriile de " .
                    ($type === 'most_detestable' ? "detestabilitate" : "non-detestabilitate") .
                    " pentru pragul de " . $centralThreshold . "%.";

                if ($format === 'json') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'title' => 'Fără rezultate',
                        'description' => $noResults,
                        'items' => []
                    ]);
                } else {
                    header('Content-Type: application/xml; charset=utf-8');
                    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                    echo '<rss version="2.0"><channel><title>Fără rezultate</title><description>' .
                        $noResults . '</description></channel></rss>';
                }
                exit;
            }

            // Obtine metadatele pentru clasament
            $metadata = $this->getRankingsMetadata($type, $centralThreshold);

            // Genereaza output in formatul solicitat
            if ($format === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                echo $this->generateJsonOutput($filteredEntities, $metadata, $type);
            } else {
                header('Content-Type: application/xml; charset=utf-8');
                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                echo $this->generateSimpleRssXml($filteredEntities, $metadata, $type);
            }

        } catch (PDOException $e) {
            // Tratare erori de baza de date
            if ($format === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
            } else {
                header('Content-Type: application/xml; charset=utf-8');
                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                echo '<error>Query failed: ' . htmlspecialchars($e->getMessage()) . '</error>';
            }
        }
        exit;
    }

    // Calculeaza scorurile de detestabilitate pentru entitati
    private function calculateEntityDesirability($entities, $ratingThreshold = 3.5)
    {
        foreach ($entities as &$entity) {
            $traits = $this->model->getEntityTraits($entity['id']);

            $totalTraits = count($traits);
            $positiveTraits = 0;

            foreach ($traits as &$trait) {
                $trait['is_positive'] = $trait['avg_rating'] >= $ratingThreshold;
                if ($trait['is_positive']) {
                    $positiveTraits++;
                }
            }

            if ($totalTraits > 0) {
                $positivePercentage = round(($positiveTraits / $totalTraits) * 100, 2);
                $isDesirable = $positivePercentage >= 60;
                $detestabilityScore = round(100 - $positivePercentage, 2);
            } else {
                $positivePercentage = 0;
                $isDesirable = false;
                $detestabilityScore = 100;
            }

            $entity['traits'] = $traits;
            $entity['total_traits'] = $totalTraits;
            $entity['positive_traits'] = $positiveTraits;
            $entity['positive_percentage'] = $positivePercentage;
            $entity['is_desirable'] = $isDesirable;
            $entity['detestability_score'] = $detestabilityScore;
        }

        return $entities;
    }

    //Filtreaza si sorteaza entitatile in functie de scorul de detestabilitate
    private function filterAndSortEntities($entities, $type, $centralThreshold = 50.0)
    {
        $filteredEntities = [];

        //filtrare
        foreach ($entities as $entity) {
            if ($type === 'most_detestable' && $entity['detestability_score'] >= $centralThreshold) {
                $filteredEntities[] = $entity;
            } else if ($type === 'least_detestable' && $entity['detestability_score'] < $centralThreshold) {
                $filteredEntities[] = $entity;
            }
        }

        //sortare
        if ($type === 'most_detestable') {
            usort($filteredEntities, function ($a, $b) {
                if ($a['detestability_score'] == $b['detestability_score']) {
                    return $a['avg_rating'] <=> $b['avg_rating'];
                }
                return $b['detestability_score'] <=> $a['detestability_score'];
            });
        } else { // least_detestable
            usort($filteredEntities, function ($a, $b) {
                if ($a['detestability_score'] == $b['detestability_score']) {
                    return $b['avg_rating'] <=> $a['avg_rating'];
                }
                return $a['detestability_score'] <=> $b['detestability_score'];
            });
        }

        return $filteredEntities;
    }

    //Obtine metadatele pentru clasament
    private function getRankingsMetadata($type, $centralThreshold)
    {
        if ($type === 'most_detestable') {
            return [
                'title' => 'Cele Mai Detestabile Entități',
                'description' => 'Clasamentul entităților cu cele mai multe trăsături negative și cel mai mare scor de detestabilitate (detestabilitate ≥ ' . $centralThreshold . '%)'
            ];
        } else { // least_detestable
            return [
                'title' => 'Cele Mai Puțin Detestabile Entități',
                'description' => 'Clasamentul entităților cu cele mai multe trăsături pozitive și cel mai mic scor de detestabilitate (detestabilitate < ' . $centralThreshold . '%)'
            ];
        }
    }

    //Genereaza output in format JSON
    private function generateJsonOutput($entities, $metadata, $type)
    {
        $result = [
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'count' => count($entities),
            'items' => []
        ];

        foreach ($entities as $index => $entity) {
            $position = $index + 1;

            $item = [
                'name' => $entity['name'],
                'category' => $entity['category_name'],
                'positive_traits_count' => $entity['positive_traits'],
                'negative_traits_count' => $entity['total_traits'] - $entity['positive_traits'],
                'total_traits' => $entity['total_traits']
            ];

            //adaugam scorul de detestabilitate sau pozitivitate
            if ($type === 'most_detestable') {
                $item['detestability_percentage'] = $entity['detestability_score'];
            } else {
                $item['desirability_percentage'] = $entity['positive_percentage'];
            }

            $result['items'][] = $item;
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }


    //Genereaza output in format RSS XML
    private function generateSimpleRssXml($entities, $metadata, $type)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        //creeaza elementul <rss> cu namespace-ul atom
        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $dom->appendChild($rss);

        //creeaza canalul rss
        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        //adresa completa a feedului
        $feedUrl = 'http://localhost/IRI_Ballerina_Cappuccina/api/rss-rankings?type=' . urlencode($type);


        //adauga <atom:link> in interiorul <channel>
        $atomLink = $dom->createElement('atom:link');
        $atomLink->setAttribute('href', $feedUrl);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        //adauga metadatele canalului
        $channel->appendChild($dom->createElement('title', $metadata['title']));
        $channel->appendChild($dom->createElement('description', $metadata['description']));
        $channel->appendChild($dom->createElement('link', 'http://' . $_SERVER['HTTP_HOST'] . '/rankings/' . $type));
        $channel->appendChild($dom->createElement('language', 'ro-RO'));
        $channel->appendChild($dom->createElement('lastBuildDate', date(DATE_RSS)));

        //adauga fiecare entitate ca element <item>
        foreach ($entities as $index => $entity) {
            $item = $dom->createElement('item');

            //titlul elementului cu pozitia si metrica principala
            $position = $index + 1;
            $titleText = "";

            if ($type === 'most_detestable') {
                $titleText = "{$position}. " . $entity['name'] . " - " . $entity['detestability_score'] . "% detestabil";
            } else { // least_detestable
                $titleText = "{$position}. " . $entity['name'] . " - " . $entity['positive_percentage'] . "% pozitiv";
            }

            $title = $dom->createElement('title', $titleText);
            $item->appendChild($title);

            // Descriere structurată folosind HTML
            $descriptionHtml = "<p><strong>Poziție în clasament:</strong> {$position}</p>";
            $descriptionHtml .= "<p><strong>Categorie:</strong> " . htmlspecialchars($entity['category_name'], ENT_QUOTES, 'UTF-8') . "</p>";
            $descriptionHtml .= "<p><strong>Rating general:</strong> " . $entity['avg_rating'] . "/5</p>";

            if ($type === 'most_detestable') {
                $descriptionHtml .= "<p><strong>Scor detestabilitate:</strong> " . $entity['detestability_score'] . "%</p>";
                $descriptionHtml .= "<p><strong>Trăsături negative:</strong> " .
                    ($entity['total_traits'] - $entity['positive_traits']) . " din " . $entity['total_traits'] . "</p>";
            } else {
                $descriptionHtml .= "<p><strong>Scor pozitivitate:</strong> " . $entity['positive_percentage'] . "%</p>";
                $descriptionHtml .= "<p><strong>Trăsături pozitive:</strong> " .
                    $entity['positive_traits'] . " din " . $entity['total_traits'] . "</p>";
            }

            if (!empty($entity['description'])) {
                $descriptionHtml .= "<p><strong>Descriere:</strong> " .
                    htmlspecialchars($entity['description'], ENT_QUOTES, 'UTF-8') . "</p>";
            }

            $description = $dom->createElement('description');
            $description->appendChild($dom->createCDATASection($descriptionHtml));
            $item->appendChild($description);

            //elemente standard RSS
            $link = 'http://' . $_SERVER['HTTP_HOST'] . '/entity/' . urlencode($entity['id']);
            $item->appendChild($dom->createElement('link', $link));
            $item->appendChild($dom->createElement('guid', $link));
            $item->appendChild($dom->createElement('pubDate', date(DATE_RSS, strtotime($entity['last_review_date']))));

            //categorie
            $category = $dom->createElement('category', htmlspecialchars($entity['category_name']));
            $item->appendChild($category);

            $channel->appendChild($item);
        }

        return $dom->saveXML($dom->documentElement);
    }
}
?>