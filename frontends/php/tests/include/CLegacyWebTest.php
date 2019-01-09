<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/CWebTest.php';

define('TEST_GOOD', 0);
define('TEST_BAD', 1);
define('TEST_ERROR', 2);

/**
 * Base class for legacy Selenium tests.
 */
class CLegacyWebTest extends CWebTest {
	const WAIT_ITERATION = 50;

	// List of strings that should NOT appear on any page.
	public $failIfExists = [
		'pg_query',
		'Error in',
		'expects parameter',
		'Undefined index',
		'Undefined variable',
		'Undefined offset',
		'Fatal error',
		'Call to undefined method',
		'Invalid argument supplied',
		'Missing argument',
		'Warning:',
		'PHP notice',
		'PHP warning',
		'Use of undefined',
		'You must login',
		'DEBUG INFO',
		'Cannot modify header',
		'Parse error',
		'syntax error',
		'Try to read inaccessible property',
		'Illegal string offset',
		'must be an array'
	];

	// List of strings that SHOULD appear on every page.
	public $failIfNotExists = [
		'Zabbix Share',
		'Help',
		'Admin',
		'Sign out'
	];

	protected function putBreak() {
		fwrite(STDOUT, "\033[s    \033[93m[Breakpoint] Press \033[1;93m[RETURN]\033[0;93m to continue...\033[0m");
		while (fgets(STDIN, 1024) == '') {}
		fwrite(STDOUT, "\033[u");

		return;
	}

	public function authenticate() {
		$this->page->login('09e7d4286dfdca4ba7be15e0f3b2b55a', 1);
	}

	public function authenticateUser($sessionid, $userId) {
		$this->page->login($sessionid, $userId);
	}

	public function zbxTestOpen($url) {
		$this->page->open($url);
	}

	public function zbxTestLogin($url, $server_name = true) {
		global $ZBX_SERVER_NAME;

		$this->page->login()->open($url);

		if ($server_name && $ZBX_SERVER_NAME !== '') {
			$this->zbxTestWaitUntilMessageTextPresent('server-name', $ZBX_SERVER_NAME);
		}

		$this->zbxTestTextNotPresent('Login name or password is incorrect');
	}

	public function zbxTestLogout() {
		$this->query('xpath://a[@class="top-nav-signout"]')->one()->click();
	}

	public function zbxTestCheckFatalErrors() {
		$this->zbxTestTextNotPresent($this->failIfExists);
	}

	public function zbxTestCheckMandatoryStrings() {
		$this->zbxTestTextPresent($this->failIfNotExists);
	}

	public function zbxTestCheckTitle($title, $check_server_name = true) {
		global $ZBX_SERVER_NAME;

		if ($check_server_name && $ZBX_SERVER_NAME !== '') {
			$title = $ZBX_SERVER_NAME.NAME_DELIMITER.$title;
		}

		$this->assertEquals($title, $this->page->getTitle());
	}

	public function zbxTestCheckHeader($header) {
		$this->assertEquals($header, $this->query('tag:h1')->waitUntilVisible()->one()->getText());
	}

	public function zbxTestHeaderNotPresent($header) {
		$this->assertFalse($this->zbxTestIsElementPresent("//h1[contains(text(),'".$header."')]"), '"'.$header.'" must not exist.' );
	}

	public function zbxTestTextPresent($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}
		$page_source = $this->page->getSource();

		foreach ($strings as $string) {
			if (empty($string)) {
				$this->assertTrue(true);
			}
			else {
				$this->assertTrue(strstr($page_source, $string) !== false, '"'.$string.'" must exist.');
			}
		}
	}

	public function zbxTestTextNotPresent($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			$this->assertTrue($this->query('xpath://*[contains(text(),"'.$string.'")]')->count() === 0, '"'.$string.'" must not exist.');
		}
	}

	public function zbxTestTextPresentInMessageDetails($strings) {
		$this->query('class:msg-details')->waitUntilVisible();
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			$quote = '"';
			if (strpos($string, $quote) !== false) {
				$quote = '\'';
				if (strpos($string, $quote) !== false) {
					$this->fail('Cannot assert message detail text containig both single and double quotes.');
				}
			}

			$this->zbxTestAssertElementPresentXpath('//div[@class="msg-details"]//li[contains(text(), '.$quote.$string.$quote.')]');
		}
	}

	public function zbxTestTextVisibleOnPage($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			if (!empty($string)) {
				$this->assertTrue($this->query('xpath://*[contains(text(),"'.$string.'")]')->count() !== 0, '"'.$string.'" must exist.');
			}
		}
	}

	public function zbxTestTextNotVisibleOnPage($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			$elements = $this->query('xpath://*[contains(text(),"'.$string.'")]')->all();
			foreach ($elements as $element) {
				$this->assertFalse($element->isDisplayed());
			}
		}
	}

	public function zbxTestClickLinkText($link_text) {
		$this->query('link:'.$link_text)->one()->click();
	}

	public function zbxTestClickLinkTextWait($link_text) {
		$this->query('link:'.$link_text)->waitUntilVisible()->one()->click();
	}

	public function zbxTestDoubleClickLinkText($link_text, $id) {
		$this->zbxTestClickLinkTextWait($link_text);
		if (!$this->zbxTestElementPresentId($id)){
			$this->zbxTestClickLinkTextWait($link_text);
		}
	}

	public function zbxTestClickButtonText($button_text) {
		$this->query('xpath://button[contains(text(),"'.$button_text.'")]')->waitUntilPresent()->one()->click();
	}

	public function zbxTestClick($id) {
		$this->query('id:'.$id)->one()->click();
	}

	public function zbxTestClickWait($id) {
		$this->query('id:'.$id)->waitUntilPresent()->one()->click();
	}

	public function zbxTestDoubleClick($click_id, $id) {
		$this->zbxTestClickWait($click_id);
		if (!$this->zbxTestElementPresentId($id)){
			$this->zbxTestClickWait($click_id);
		}
	}

	public function zbxTestDoubleClickBeforeMessage($click_id, $id) {
		$this->zbxTestClickWait($click_id);

		if (!$this->zbxTestElementPresentId($id) && $this->query('class:msg-bad')->count() === 0){
			$this->zbxTestClickWait($click_id);
		}
	}

	public function zbxTestClickXpath($xpath) {
		$this->query('xpath:'.$xpath)->one()->click();
	}

	public function zbxTestClickXpathWait($xpath) {
		$this->query('xpath:'.$xpath)->waitUntilClickable()->one()->click();
	}

	public function zbxTestDoubleClickXpath($click_xpath, $id) {
		$this->zbxTestClickXpathWait($click_xpath);
		if (!$this->zbxTestElementPresentId($id)){
			$this->zbxTestClickXpathWait($click_xpath);
		}
	}

	public function zbxTestHrefClickWait($href) {
		$this->query('xpath:'."//a[contains(@href,'$href')]")->one()->click();
	}

	public function zbxTestCheckboxSelect($id, $select = true) {
		$checkbox = $this->query('id:'.$id)->waitUntilPresent()->one();
		if ($select != $checkbox->isSelected()) {
			$checkbox->click();
		}
	}

	public function zbxTestCheckboxSelected($id) {
		return $this->query('id:'.$id)->waitUntilPresent()->one()->isSelected();
	}

	public function zbxTestClickButton($value) {
		$this->query('xpath:'."//button[@value='".$value."']")->waitUntilClickable()->one()->click();
	}

	/**
	 * Clicks on the "Select" button to the right of the multiselect
	 *
	 * @param string $id  ID of the multiselect.
	 */
	public function zbxTestClickButtonMultiselect($id) {
		$this->zbxTestClickXpath(
			"//div[contains(@class, 'multiselect') and @id='$id']/../div[@class='multiselect-button']/button"
		);
	}

	public function zbxTestMultiselectNew($id, $string) {
		$xpath = 'xpath://div[contains(@class, "multiselect") and @id="'.$id.'"]/input';
		$this->query($xpath)->one()->clear()->sendKeys($string);
		$this->zbxTestClickXpathWait(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='available']".
			"/ul[@class='multiselect-suggest']/li[@data-id='$string']"
		);

		$this->zbxTestMultiselectAssertSelected($id, $string.' (new)');
	}

	public function zbxTestMultiselectAssertSelected($id, $string) {
		$this->zbxTestAssertVisibleXpath(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='selected']".
			"/ul[@class='multiselect-list']/li/span[@class='subfilter-enabled']/span[text()='$string']"
		);
	}

	/**
	 * Removes one element from the multiselect
	 *
	 * @param string $id		ID of the multiselect
	 * @param string $string	Element name to be removed
	 */
	public function zbxTestMultiselectRemove($id, $string) {
		$this->zbxTestClickXpathWait(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='selected']".
			"/ul[@class='multiselect-list']/li/span[@class='subfilter-enabled']/span[text()='$string']/..".
			"/span[@class='subfilter-disable-btn']"
		);
		$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='selected']".
			"/ul[@class='multiselect-list']/li/span[@class='subfilter-enabled']/span[text()='$string']"
		));
	}

	/**
	 * Clears all elements from the multiselect
	 *
	 * @param string $id	ID of the multiselect
	 */
	public function zbxTestMultiselectClear($id) {
		$xpath = '//div[contains(@class, "multiselect") and @id="'.$id.'"]'.
				'/div[@class="selected"]/ul[@class="multiselect-list"]/li'.
				'//span[@class="subfilter-disable-btn"]';
		$locator = WebDriverBy::xpath($xpath);
		$elements = $this->webDriver->findElements($locator);

		if ($elements) {
			foreach ($elements as $element) {
				$element->click();
			}

			$this->zbxTestWaitUntilElementNotVisible($locator);
		}
	}

	/**
	 * Open another filter tab
	 */
	public function zbxTestExpandFilterTab($string = 'Filter') {
		if ($string === 'Filter') {
			$element = $this->query('xpath', "//div[contains(@class,'table filter-forms')]")->one()->isDisplayed();
			if (!$element) {
				$this->zbxTestClickXpathWait("//a[contains(@class,'filter-trigger')]");
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//div[contains(@class,'table filter-forms')]"));
			}
		}
		else {
			$element = $this->query('xpath', "//div[@class='time-input']")->one()->isDisplayed();
			if (!$element) {
				$this->zbxTestClickXpathWait("//a[contains(@class,'btn-time')]");
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//div[@class='time-input']"));
			}
		}
	}

	public function zbxTestInputType($id, $str) {
		$this->query('id:'.$id)->one()->clear()->sendKeys($str);
	}

	public function zbxTestInputTypeOverwrite($id, $str) {
		$element = $this->query('id:'.$id)->waitUntilVisible()->one();

		$element->click();
		$this->page->keyPress([WebDriverKeys::CONTROL, 'a', WebDriverKeys::CONTROL]);
		$element->sendKeys($str);
	}

	public function zbxTestInputTypeByXpath($xpath, $str, $validate = true) {
		$this->query('xpath:'.$xpath)->waitUntilVisible()->one()->sendKeys($str);

		if ($validate) {
			$this->zbxTestWaitUntilElementValuePresent(WebDriverBy::xpath($xpath), $str);
		}
	}

	public function zbxTestInputClearAndTypeByXpath($xpath, $str) {
		$this->query('xpath:'.$xpath)->waitUntilVisible()->one()->clear()->sendKeys($str);
	}

	public function zbxTestInputTypeWait($id, $str) {
		$this->query('id:'.$id)->waitUntilVisible()->one()->sendKeys($str);
	}

	public function zbxTestDropdownHasOptions($id, array $strings) {
		$values = [];
		foreach ($this->getDropdownOptions($id) as $option) {
			$values[] = $option->getText();
		}

		$this->assertTrue(count(array_diff($strings, $values)) === 0);
	}

	public function zbxTestDropdownSelect($id, $string) {
		// Simplified escaping of xpath string.
		if (strpos($string, '"') !== false) {
			$string = '\''.$string.'\'';
		}
		else {
			$string = '"'.$string.'"';
		}

		$option = $this->getDropdown($id)->query('xpath:.//option[text()='.$string.']')->one();

		if (!$option->isSelected()) {
			$option->click();

			return $option;
		}

		return null;
	}

	public function zbxTestDropdownSelectWait($id, $string) {
		$option = $this->zbxTestDropdownSelect($id, $string);

		if ($option !== null) {
			try {
				$option->waitUntilSelected();
			} catch (StaleElementReferenceException $e) {
				// Element not found in the cache, looks like page changed.
				$this->zbxTestWaitForPageToLoad();
			}
		}
	}

	public function zbxTestDropdownAssertSelected($name, $text) {
		$this->assertEquals($text, $this->zbxTestGetSelectedLabel($name));
	}

	public function zbxTestGetSelectedLabel($id) {
		foreach ($this->getDropdownOptions($id) as $option) {
			if ($option->isSelected()) {
				return $option->getText();
			}
		}
	}

	public function zbxTestElementPresentId($id) {
		return ($this->query('id:'.$id)->count() !== 0);
	}

	public function zbxTestAssertElementPresentId($id) {
		if ($this->query('id:'.$id)->count() === 0) {
			$this->fail("Element was not found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestAssertElementPresentXpath($xpath) {
		if ($this->query('xpath:'.$xpath)->count() === 0) {
			$this->fail("Element was not found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestAssertAttribute($xpath, $attribute, $value = 'true') {
		$this->assertEquals($this->query('xpath:'.$xpath)->one()->getAttribute($attribute), $value);
	}

	public function zbxTestAssertElementNotPresentId($id) {
		if ($this->query('id:'.$id)->count() !== 0) {
			$this->fail("Element was found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestAssertElementNotPresentXpath($xpath) {
		if ($this->query('xpath:'.$xpath)->count() !== 0) {
			$this->fail("Element was found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestIsElementPresent($xpath) {
		return ($this->query('xpath:'.$xpath)->count() > 0);
	}

	public function getQueryFromBy($by) {
		$mapping = [
			'class name' => 'class',
			'css selector' => 'css',
			'link text' => 'link',
			'partial link text' => 'link',
			'tag name' => 'tag'
		];

		$type = $by->getMechanism();
		$locator = $by->getValue();

		if (array_key_exists($type, $mapping)) {
			$type = $mapping[$type];
		}

		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$caller = $trace[2];

		if ($caller['class'] !== __CLASS__) {
			$this->addWarning('Web driver selector should not be used in test cases.');
		}

		return $this->query($type, $locator);
	}

	public function zbxTestWaitUntilElementVisible($by) {
		$this->getQueryFromBy($by)->waitUntilVisible();
	}

	public function zbxTestWaitUntilElementValuePresent($by, $value) {
		$this->getQueryFromBy($by)->waitUntilAttributesPresent(['value' => $value]);
	}

	public function zbxTestWaitUntilElementNotVisible($by) {
		$this->getQueryFromBy($by)->waitUntilNotVisible();
	}

	public function zbxTestWaitUntilElementClickable($by) {
		$this->getQueryFromBy($by)->waitUntilClickable();
	}

	public function zbxTestWaitUntilElementPresent($by) {
		$this->getQueryFromBy($by)->waitUntilPresent();
	}

	public function zbxTestWaitUntilMessageTextPresent($css, $string) {
		$this->query('class:'.$css)->waitUntilTextPresent($string);
	}

	public function zbxTestTabSwitch($tab) {
		$this->zbxTestClickXpathWait("//div[@id='tabs']/ul/li/a[text()='$tab']");
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//li[contains(@class, 'ui-tabs-active')]/a[text()='$tab']"));
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestTabSwitchById($id, $tab) {
		$this->zbxTestClickWait($id);
		if ($this->zbxTestGetText("//li[contains(@class, 'ui-tabs-active')]/a") != $tab ) {
			$this->zbxTestClickXpathWait("//div[@id='tabs']/ul/li/a[text()='$tab']");
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//li[contains(@class, 'ui-tabs-active')]/a[text()='$tab']"));
		}
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestLaunchOverlayDialog($header) {
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@id='overlay_dialogue']/div[@class='dashbrd-widget-head']/h4[text()='$header']"));
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestClickAndAcceptAlert($id) {
		$this->zbxTestClickWait($id);
		try {
			$this->zbxTestAcceptAlert();
		}
		catch (TimeoutException $ex) {
			$this->zbxTestClickWait($id);
			$this->zbxTestAcceptAlert();
		}
	}

	public function zbxTestAcceptAlert() {
		$this->page->acceptAlert();
		$this->zbxTestWaitForPageToLoad();
	}

	public function zbxTestDismissAlert() {
		$this->page->dismissAlert();
	}

	public function zbxTestGetDropDownElements($dropdownId) {
		$elements = [];
		foreach ($this->getDropdownOptions($dropdownId) as $option) {
			$elements[] = [
				'id' => $option->getAttribute('value'),
				'content' => $option->getText()
			];
		}

		return $elements;
	}

	public function zbxTestAssertElementValue($id, $value) {
		$this->assertEquals($value, $this->query('id:'.$id)->waitUntilPresent()->one()->getAttribute('value'));
	}

	public function zbxTestGetValue($xpath) {
		return $this->zbxTestGetAttributeValue($xpath, 'value');
	}

	public function zbxTestGetAttributeValue($xpath, $attribute) {
		return $this->query('xpath:'.$xpath)->one()->getAttribute($attribute);
	}

	public function zbxTestGetText($xpath) {
		return $this->query('xpath:'.$xpath)->one()->getText();
	}

	public function zbxTestAssertElementText($xpath, $text){
		$element = $this->query('xpath:'.$xpath)->waitUntilVisible()->one()->getText();
		$element_text = trim(preg_replace('/\s+/', ' ', $element));
		$this->assertEquals($text, $element_text);
	}

	public function zbxTestAssertNotVisibleId($id){
		$this->assertFalse($this->query('id', $id)->one()->isDisplayed());
	}

	public function zbxTestAssertNotVisibleXpath($xpath){
		$this->assertFalse($this->query('xpath', $xpath)->one()->isDisplayed());
	}

	public function zbxTestAssertVisibleId($id){
		$this->assertTrue($this->query('id', $id)->one()->isDisplayed());
	}

	public function zbxTestAssertVisibleXpath($xpath){
		$this->assertTrue($this->query('xpath', $xpath)->one()->isDisplayed());
	}

	public function zbxTestIsEnabled($xpath){
		$this->assertTrue($this->query('xpath', $xpath)->one()->isEnabled());
	}

	// check that page does not have real (not visible) host or template names
	public function zbxTestCheckNoRealHostnames() {
		$result = DBselect(
			'SELECT host'.
			' FROM hosts'.
			' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')'.
				' AND name <> host'
		);
		while ($row = DBfetch($result)) {
			$this->zbxTestTextNotPresent($row['host']);
		}
	}

	public function zbxTestWaitForPageToLoad() {
		$this->page->waitUntilReady();
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific text.
	 *
	 * @param string $text  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickText($text) {
		$xpath = "//div[contains(@class, 'header-title')]".
				"//nav[@aria-label='Content controls']".
					"//button[text()='{$text}']";

		$this->zbxTestClickXpathWait($xpath);
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific class name.
	 *
	 * @param string $class  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickClass($class) {
		$xpath = "//div[contains(@class, 'header-title')]".
				"//nav[@aria-label='Content controls']".
					"//button[contains(@class, '{$class}')]";

		$this->zbxTestClickXpath($xpath);
	}

	/**
	 * Select option for select element inside 'Main filter' area.
	 *
	 * @param string $name   Select tag name attribute.
	 * @param string $value  Option value to select.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestMainFilterDropdownSelect($name, $value) {
		$xpath = "//div[contains(@class, 'header-title')]".
				"//form[@aria-label='Main filter']".
					"//select[@name='{$name}']".
						"/option[@value='{$value}']";

		$this->zbxTestClickXpath($xpath);
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific text.
	 *
	 * @param string $text  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickTextWait($text) {
		$xpath = "//div[contains(@class, 'header-title')]".
					"//nav[@aria-label='Content controls']".
						"//button[text()='{$text}']";

		$this->zbxTestClickXpathWait($xpath);
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific class name.
	 *
	 * @param string $class  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickClassWait($class) {
		$xpath = "//div[contains(@class, 'header-title')]".
					"//nav[@aria-label='Content controls']".
						"//button[contains(@class, '{$class}')]";

		$this->zbxTestClickXpathWait($xpath);
	}

	/**
	 * Select option for select element inside 'Main filter' area.
	 *
	 * @param string $name   Select tag name attribute.
	 * @param string $value  Option value to select.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestMainFilterDropdownSelectWait($name, $value) {
		$xpath = "//div[contains(@class, 'header-title')]".
					"//form[@aria-label='Main filter']".
						"//select[@name='{$name}']".
							"/option[@value='{$value}']";

		$this->zbxTestClickXpathWait($xpath);
	}

	/**
	 * Get dropdown element by id or name.
	 *
	 * @param string $id    dropdown id or name
	 *
	 * @return WebDriverElement
	 */
	protected function getDropdown($id) {
		foreach (['id', 'name'] as $type) {
			foreach ($this->query($type, $id)->all() as $element) {
				if ($element->getTagName() === 'select') {
					return $element;
				}
			}
		}

		$this->fail('Dropdown element "'.$id.'" was not found!');
	}

	/**
	 * Get dropdown option elements by dropdown id or name.
	 *
	 * @param string $id    dropdown id or name
	 *
	 * @return array of WebDriverElement
	 */
	protected function getDropdownOptions($id) {
		return $this->getDropdown($id)->findElements(WebDriverBy::tagName('option'));
	}

	public function __get($attribute) {
		if ($attribute === 'webDriver') {
			$this->addWarning('Web driver should not be accessed directly from test cases.');
			return CElementQuery::getDriver();
		}

		parent::__get($attribute);
	}
}
