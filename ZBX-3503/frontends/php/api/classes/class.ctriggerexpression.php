<?php
class CTriggerExpression extends CExpression {

	public function __construct($trigger){
		$this->initializeVars();
		$this->checkExpression($trigger['expression']);
	}


	protected function initializeVars(){
		$this->allowed = INIT_TRIGGER_EXPRESSION_STRUCTURES();

		$this->errors = array();
		$this->expressions = array();
		$this->data = array(
			'hosts'=>array(),
			'usermacros'=>array(),
			'macros'=>array(),
			'items'=>array(),
			'itemParams'=>array(),
			'functions'=>array(),
			'functionParams'=>array()
		);

		$this->newExpr = array(
			'part' => array(
				'expression' => false,
				'usermacro' => false,
				'host' => false,
				'item' => false,
				'itemParam' => false,
				'function' => false,
				'functionParam' => false,
			),
			'object' => array(
				'expression' => '',
				'macro' => '',
				'usermacro' => '',
				'host' => '',
				'item' => '',
				'itemParam' => '',
				'itemParamReal' => '',
				'itemParamList' => '',
				'function' => '',
				'functionName' => '',
				'functionParam' => '',
				'functionParamReal' => '',
				'functionParamList' => ''
			),
			'params' => array(
				'quoteClose' => false,
				'comma' => 0,
				'count' => 0,
				'item' => array(),
				'function' => array()
			)
		);
		$this->currExpr = $this->newExpr;

		$this->symbols = array(
			'sequence' => 0,
			'open' => array(
				'(' => 0,		// parenthesis
				'{' => 0		// curly brace
			),
			'close' => array(
				')' => 0,		// parenthesis
				'}' => 0,		// curly brace
			),
			'linkage' => array(
				'+' => 0,		// addition
				'-' => 0,		// subtraction
				'*' => 0,		// multiplication
				'/' => 0,		// division
				'#' => 0,		// not equals
				'=' => 0,		// equals
				'<' => 0,		// less than
				'>' => 0,		// greater than
				'&' => 0,		// logical and
				'|' => 0,		// logical or
			),
			'expr' => array(
				'$' => 0,		// dollar
				'\\' => 0,		// backslash
				':' => 0,		// colon
				'.' => 0,		// dot
			),
			'params' => array(
				'"' => 0,		// quote
				'[' => 0,		// open square brace
				']' => 0,		// close square brace
				'(' => 0,		// open brace
				')' => 0		// close brace
			)
		);

		$this->previous = array(
			'sequence' => '',
			'last' => '',
			'prelast' => '',
			'lastNoSpace' => '',
			'preLastNoSpace' => ''
		);
	}


	public function checkExpression($expression){
		$length = zbx_strlen($expression);
		$symbolNum = 0;

		try{
			if(zbx_empty(trim($expression)))
				throw new Exception('Empty expression.');

// Check expr start symbol
			$startSymbol = zbx_substr(trim($expression), 0, 1);
			if(($startSymbol != '(') && ($startSymbol != '{') && ($startSymbol != '-') && !zbx_ctype_digit($startSymbol))
				throw new Exception('Incorrect trigger expression.');

			for($symbolNum = 0; $symbolNum < $length; $symbolNum++){
				$symbol = zbx_substr($expression, $symbolNum, 1);
				$this->parseOpenParts($this->previous['last']);
				$this->parseCloseParts($symbol);
				if($this->inParameter($symbol)){
					$this->setPreviousSymbol($symbol);
					continue;
				}

				$this->checkSymbolSequence($symbol);
				$this->setPreviousSymbol($symbol);
			}

			$symbolNum = 0;

			$simpleExpression = $expression;
			$this->checkBraces();
			$this->checkParts($simpleExpression);
			$this->checkSimpleExpression($simpleExpression);
		}
		catch(Exception $e){
			$symbolNum = ($symbolNum > 0) ? --$symbolNum : $symbolNum;

			$this->errors[] = $e->getMessage();
			$this->errors[] = 'Check expression part starting from " '.zbx_substr($expression, $symbolNum).' "';
		}
	}




}
?>
