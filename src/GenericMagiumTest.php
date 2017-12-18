<?php

namespace Magium\Clairvoyant\GenericTests;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Magium\AbstractTestCase;
use Magium\Clairvoyant\Capture\PageInformation;
use Magium\Clairvoyant\Listener\GenericClairvoyantAdapter;
use Zend\Uri\Uri;

class GenericMagiumTest extends AbstractTestCase
{

    protected $magiumTestUrl;

    protected $linkXpaths = [];

    protected $testHostname;

    protected $testMainUrl;

    protected $clickCount = 0;

    protected $maxLinkCount = 25;

    protected $popupCloseAttempted = false;

    public function getUrl()
    {
        if (!$this->magiumTestUrl && isset($_SERVER['MAGIUM_BASE_URL'])) {
            $this->magiumTestUrl = $_SERVER['MAGIUM_BASE_URL'];
        }
        if (!$this->magiumTestUrl) {
            throw new \Exception('You must either set the MAGIUM_BASE_URL environment variable or override the ' . __CLASS__ . ' class');
        }
        return $this->magiumTestUrl;
    }

    public function testSite()
    {
        $writer = $this->get(GenericClairvoyantAdapter::class);
        /** @var $writer GenericClairvoyantAdapter */
        $writer->setTestTitle('Generic Magium Test');
        $writer->setTestDescription('This is a generic test that finds the first 10 links, preferably navigation links, on the home page and clicks on them');

        $this->startTimer();
        $this->commandOpen($this->getUrl());
        $this->endTimer($this->getWebdriver()->getTitle());
        $this->get(PageInformation::class)->capture();

        $this->testMainUrl = $this->webdriver->getCurrentURL();
        $uri = new Uri($this->testMainUrl);
        $this->testHostname = $uri->getHost();

        $this->getLinks();

        while (($element = $this->getNextLink()) !== null) {
            try {
                $this->sleep('1000ms'); // Give it some breathing room
                $this->startTimer();
                $linkLabel = $this->getElementValue($element);
                $element->click();
                if (!$linkLabel) {
                    $linkLabel = trim($this->getWebdriver()->getTitle());
                }
                $this->endTimer($linkLabel);
                $this->get(PageInformation::class)->capture();
                $this->clickCount++;
            } catch (\Exception $e) {
                $this->clickClose();
            }
        }
    }

    protected function getLinks()
    {
        // Favor <nav /> elements
        $links = $this->getWebdriver()->findElements(WebDriverBy::xpath('//nav/descendant::a[@href]'));
        foreach ($links as $link) {
            $this->addLink($link);
        }
        $links = $this->getWebdriver()->findElements(
            WebDriverBy::xpath(
                '//*[@id="nav" or contains(concat(" ", @class, " "), " nav ") or contains(concat(" ", @class, " "), " navigation ") '
                . ' or contains(concat(" ", @class, " "), " main-navigation ")]/descendant::a[@href]'
            )
        );
        foreach ($links as $link) {
            $this->addLink($link);
        }

        // The favor elements that could be a <nav >

        $links = $this->getWebdriver()->findElements(WebDriverBy::xpath('//a[@href]'));
        foreach ($links as $link) {
            $this->addLink($link);
        }
    }

    protected function clickClose()
    {
        try {
            $this->getWebdriver()->action()->moveByOffset(10, 10);
            $this->getWebdriver()->getMouse()->click();
            sleep(2);
        } catch (\Exception $e) {

        }
    }

    protected function getElementValue(WebDriverElement $element)
    {
        $value = $element->getText();
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);
        return $value;
    }

    protected function getNextLink(): ?WebDriverElement
    {
        while (count($this->linkXpaths) > 0 && $this->clickCount < $this->maxLinkCount) {
            $xpath = array_shift($this->linkXpaths);
            if ($xpath) {

                // First try the link on the existing page
                $element = $this->getNextElement($xpath);
                if ($element) {
                    return $element;
                }

                // If we're at the main URL we do not need to open the command.  It's just going to fail.
                if ($this->testMainUrl == $this->getWebdriver()->getCurrentURL()) {
                    return null;
                }

                // If the element cannot be clicked on from the current page go back to the base URL
                $this->commandOpen($this->getUrl());
                $element = $this->getNextElement($xpath);
                if ($element) {
                    return $element;
                }
            }
        }
        return null;
    }

    protected function getNextElement($xpath)
    {
        $elements = $this->getWebdriver()->findElements(WebDriverBy::xpath($xpath));
        if ($elements && ($element = array_shift($elements)) && $element->isDisplayed()) {
            $this->clickCount++;
            return $element;
        }
        return null;
    }

    protected function addLink(WebDriverElement $element)
    {
        if ($element->isDisplayed()) {
            $xpath = $this->getXpathNodeSpec($element);
            if (!in_array($xpath, $this->linkXpaths)) {
                $elements = $this->getWebdriver()->findElements(WebDriverBy::xpath($xpath));
                if ($elements) {
                    foreach ($elements as $index => $element) {
                        if ($element->isDisplayed()) {
                            $link = $element->getAttribute('href');
                            $url = new Uri($link);
                            $host = $url->getHost();

                            // Make sure it's for the same site
                            if ($host == $this->testHostname) {
                                $finalXpath = sprintf('(%s)[%s]', $xpath, ($index + 1));
                                if (!in_array($finalXpath, $this->linkXpaths)) {
                                    $this->linkXpaths[] = $finalXpath;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function getXpathNodeSpec(WebDriverElement $element, $text = null)
    {
        if ($text && strpos($text, '""')) { // Xpath doesn't like quotes
            $text = null; // We'll just ignore it then
        }
        $name = $element->getTagName();
        if ($name == 'html') {
            return '/html';
        }
        $parentElement = $element->findElement(WebDriverBy::xpath('..'));
        $id = $element->getAttribute('id');
        $classes = $element->getAttribute('class');
        if ($id) {
            $name .= sprintf('[@id="%s"]', $id);
        } else if ($classes) {
            if ($text) {
                $name .= sprintf('[@class="%s" and .="%s"]', $classes, $text);
            } else {
                $name .= sprintf('[@class="%s"]', $classes);
            }
        } else if ($text) {
            $name .= sprintf('[.="%s"]', $text);
        }
        $path = sprintf('%s/%s', $this->getXpathNodeSpec($parentElement), $name);
        return $path;
    }

}
