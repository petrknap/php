<?php

namespace PetrKnap\Symfony\MarkdownWeb;

use PetrKnap\Symfony\MarkdownWeb\Service\Crawler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Extension;

class MarkdownWebTwigExtension extends Twig_Extension
{
    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @var Crawler
     */
    private $crawler;

    /**
     * @var array
     */
    private $site;

    public function __construct(\Twig_Environment $twig, Crawler $crawler)
    {
        $this->twig = $twig;
        $this->crawler = $crawler;
    }

    public function setSite(array $site)
    {
        $this->site = $site;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction(
                "render_index",
                function(array $context, $template, array $filters, $sortBy = null) {
                    return $this->twig->render($template, array_merge_recursive($context, [
                        "site" => $this->site,
                        "pages" => null === $sortBy ? $this->crawler->getIndex()->getPages($filters) : $this->crawler->getIndex()->getPages($filters, $sortBy)
                    ]));
                },
                [
                    "is_safe" => ["html"],
                    "needs_context" => true
                ]
            ),
        ];
    }

    public function getName()
    {
        return BUNDLE_ALIAS;
    }
}