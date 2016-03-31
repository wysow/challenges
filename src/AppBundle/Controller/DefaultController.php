<?php

namespace AppBundle\Controller;

use GuzzleHttp\Client;
use Goutte\Client as GoutteClient;
use Hoa\Compiler\Llk\Llk;
use Hoa\File\Read;
use Hoa\Math\Sampler\Random;
use Hoa\Regex\Visitor\Isotropic;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="wiki_crawler")
     */
    public function wikiCrawlerAction(Request $request)
    {
        $data = [];

        $genedysClient = $this->getGenedysClient();

        $genedysResponse = json_decode($genedysClient->get('/challenge/backend/crawler/api')->getBody(), true);

        $languageName = $genedysResponse['language'];

        $wikipediaCrawler = new GoutteClient();

        $languagesPageContent = $wikipediaCrawler
            ->request('GET', 'https://fr.wikipedia.org/wiki/Liste_des_langages_de_programmation')
        ;

        $languageLink = $languagesPageContent->filter('ul')->selectLink($languageName)->link();

        $wikipediaCrawler = $wikipediaCrawler->click($languageLink);

        $tableElements = $wikipediaCrawler->filter('table.infobox_v2')
            ->first()
            ->filter('tr')
            ->each(function (Crawler $node) {
                if (strpos(trim($node->text()), 'Date de première version') !== false) {
                    return $node->last();
                }
            })
        ;

        /** @var Crawler $element */
        foreach ($tableElements as $element) {
            if ($element !== null) {
                preg_match('/\d{4}/', $element->getNode(0)->childNodes->item(2)->textContent, $matches);

                $data['creation'] = $matches[0];
            }
        }

        $response = json_encode($data);

        $genedysClient->post('/challenge/backend/crawler/api', ['body' => $response]);

        return new JsonResponse($data);
    }

    /**
     * @Route("/regular-solver", name="regular_solver")
     */
    public function regularSolverAction(Request $request) {
        $data = [];

        $genedysClient = $this->getGenedysClient();

        $genedysResponse = json_decode($genedysClient->get('/challenge/backend/regexp/api')->getBody(), true);

        $grammar = new Read('file://'.realpath(getcwd() . '/../vendor/hoa/regex/Grammar.pp'));

        $compiler = Llk::load($grammar);

        $ast = $compiler->parse($genedysResponse['expression']);

        $generator = new Isotropic(new Random());

        $data[] = $generator->visit($ast);
        $data[] = $generator->visit($ast);
        $data[] = $generator->visit($ast);

        $response = json_encode($data);

        $genedysClient->post('/challenge/backend/regexp/api', ['body' => $response]);

        return new JsonResponse($data);


    }

    private function getGenedysClient() {
        return $genedysClient = new Client([
            'base_uri' => 'http://defi.genedys.com',
            'headers'  => [
                'ApiToken' => '155454d2fcd9e85ed5700e386ae5dae1'
            ]
        ]);
    }
}
