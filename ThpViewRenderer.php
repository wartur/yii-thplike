<?php

/*
 *  $Id: $
 * 	$Rev: $
 * 	$Date: $
 *  $Author: $
 */

/**
 * ThpViewRenderer class file.
 *
 * @author		Krivtsov Artur (wartur) <kav@wartur.ru> | Made in Russia
 * @copyright	Krivtsov Artur © 2010-2012
 * @link		http://thp.wartur.ru
 * @license		http://thp.wartur.ru/license
 */

/**
 * Компилятор thp синтаксиса, основанный на CViewRenderer
 * Позволяет удобно верстать приложения, делающий валидный html код в View
 * Обратно совместимый с PHP, позволяет компилировать THP-Like php код
 * 
 * @author		Krivtsov Artur (wartur) <kav@wartur.ru> | Made in Russia
 * @version		1.4
 */
class ThpViewRenderer extends CViewRenderer
{
	// Паттерны вырезания заголовков
	const PATTERN_TRUNC_BEGIN = '<!--// BEGIN -->'; // Начало, до которого отрезать
	const PATTERN_TRUNC_END = '<!--\\\\ END -->'; // Конец, после которого отрезать
	// 
	// Паттерны замены thp-like
	const PATTERN_THPLIKECODE_BEGIN = '<!--<?';
	const PATTERN_THPLIKECODE_END = '?>-->';
	const PATTERN_THPVAR_BEGIN = '{';
	const PATTERN_THPVAR_END = '}';
	// 
	// Строки замены
	const REPLACE_THPLIKECODE_BEGIN = '<?php';
	const REPLACE_THPLIKECODE_END = '?>';
	const REPLACE_THPVAR_BEGIN = '<?=$';
	const REPLACE_THPVAR_END = ';?>';
	//
	// Паттерн выделения php кода, для защиты от замены фигурных скобок внутри php, script, style, (onClock, onEtc..)
	const PATTERN_PREG_EXCEPTION = '#<\?(.+?)\?>|<style(.+?)</style>|<script(.+?)</script>|on(\w+)="(.+?)"#s';
	//
	// Паттерн выделения переменных thp кода
	const PATTERN_PREG_THPVAR = '#\{(.+?)\}#s';
	//
	// Паттерн выделения блоков thp кода
	const PATTERN_PREG_THPBLOCK = '#<!--(.+?)-->#s';
	//
	// Разделитель элементов массива
	const ARRAY_DELIMETR = '.';
	//
	// Разделитель cвойств, методов классов
	const PROP_DELIMETR = '->';
	//
	// Элемент массива по-умолчанию
	const ARRAY_DEFAULT_FIRST_ELEMENT = 'e';
	
	/**
	 * Parses the source view file and saves the results as another file.
	 * @param string $sourceFile the source view file path
	 * @param string $viewFile the resulting view file path
	 */
	protected function generateViewFile($sourceFile, $viewFile)
	{
		$contents = file_get_contents($sourceFile);

		// Отрезать лишнее
		$contents = $this->cutBeginEnd($contents);
		
		// Преобразовать Thp-like блоки PHP
		$contents = $this->compileThpLikeCode($contents);
				
		// Преобразования THP в PHP
		$contents = $this->compileThpCode($contents);
		$contents = $this->compileThpVars($contents);

		file_put_contents($viewFile, $contents);
	}
	
	/**
	 * Отрезает заголовки шаблонов, если присутсвуют операторы BRGIN, END
	 * 
	 * @param string $contents Код шаблона
	 * @return string Код шаблона
	 */
	private function cutBeginEnd($contents)
	{
		$beginTruncPos = strpos($contents, self::PATTERN_TRUNC_BEGIN);
		if(!empty($beginTruncPos))   // иначе копируем с начала строки
		{
			$beginTruncPos+= strlen(self::PATTERN_TRUNC_BEGIN);
		}

		$endTruncPos = strpos($contents, self::PATTERN_TRUNC_END);
		if(empty($endTruncPos))
		{
			// копируем до конца
			$result = substr($contents, $beginTruncPos);
		}
		else
		{
			// копируем до найденой позиции
			$truncLenght = $endTruncPos - $beginTruncPos;
			$result = substr($contents, $beginTruncPos, $truncLenght);
		}
		
		return $result;
	}
	
	/**
	 * Компилировать код THP
	 * 
	 * @param string $contents Код шаблона
	 * @return string Код шаблона
	 */
	private function compileThpCode($contents)
	{
		$result = '';
		
		$splitcount = preg_match_all(self::PATTERN_PREG_THPBLOCK, $contents, $splitmatch);
		$splitdata = preg_split(self::PATTERN_PREG_THPBLOCK, $contents);
		$splitmatch = $splitmatch[1]; // Получим нужный блок
		
		for($i = 0; $i < $splitcount; ++$i)
		{
			$result.= $splitdata[$i];
			
			$operator = trim($splitmatch[$i]);
			
			$blocktype = substr($operator, 0, 2);
			
			switch($blocktype)
			{
				case '//':
					if(strpos($operator, 'CUT') == 3)
					{
						for($n = ++$i; $n < $splitcount; ++$n)
						{
							if(trim($splitmatch[$n]) == '\\\\ CUT')
							{
								$i = $n;
								break;
							}
						}
					}
					elseif(strpos($operator, 'LOOP') == 3)
					{
						$raw_operator = trim(substr($operator, 7));
						
						$prepeare_operator = $this->replacePointToArrayInternal($raw_operator);
						
						$result.= '<?php foreach($'.$prepeare_operator.' as $'.self::ARRAY_DEFAULT_FIRST_ELEMENT.'): ?>';
					}
					elseif(strpos($operator, 'IFSET') == 3)
					{
						$raw_operator = trim(substr($operator, 8));
						
						$prepeare_operator = $this->replacePointToArrayInternal($raw_operator);
						
						$result.= '<?php if(isset($'.$prepeare_operator.')): ?>';
					}
					elseif(strpos($operator, 'IFEMPTY') == 3)
					{
						$raw_operator = trim(substr($operator, 10));
						
						$prepeare_operator = $this->replacePointToArrayInternal($raw_operator);
						
						$result.= '<?php if(empty($'.$prepeare_operator.')): ?>';
					}
					elseif(strpos($operator, 'IFTRUE') == 3)
					{
						$raw_operator = trim(substr($operator, 9));
						
						$prepeare_operator = $this->replacePointToArrayInternal($raw_operator);
						
						$result.= '<?php if($'.$prepeare_operator.'): ?>';
					}
					else
					{
						$result.= '<!--'.$operator.'-->';
					}
					break;
				case '\\\\':
					if(strpos($operator, 'IF') == 3)
					{
						$result.= '<?php endif; ?>';
					}
					elseif(strpos($operator, 'LOOP') == 3)
					{
						$result.= '<?php endforeach; ?>';
					}
					else
					{
						$result.= '<!--'.$operator.'-->';
					}
					break;
				case '||':
					if(strpos($operator, 'ELSE') == 3)
					{
						$result.= '<?php else: ?>';
					}
					break;
				case '!!':
						// Вырезение THP комментариев, никаких действий не нужно
					break;
				default:
					$result.= '<!--'.$operator.'-->';
					break;
			}
		}
		
		$result.= $splitdata[$splitcount];
		
		return $result;
	}
	
	/**
	 * Компилирвать переменные THP
	 * Компилирование происходит исключая код PHP который присутствует
	 * на странице, то есть исключается возможность замены фигурных скобок
	 * внутри php
	 * 
	 * @param string $contents Код шаблона
	 * @return string Код шаблона
	 */
	private function compileThpVars($contents)
	{
		$result = '';
		
		// находим все php блоки, исключаем их из замены
		$splitcount = preg_match_all(self::PATTERN_PREG_EXCEPTION, $contents, $splitmatch);
		$splitdata = preg_split(self::PATTERN_PREG_EXCEPTION, $contents);
		$splitmatch = $splitmatch[0]; // Получим нужный блок
		//
		// генерация результирующего файла
		for($i = 0; $i < $splitcount; ++$i)
		{
			// Взять и обработать блок
			$varcontent = $this->replacePointToArray($splitdata[$i]);
			$varcontent = str_replace(self::PATTERN_THPVAR_BEGIN, self::REPLACE_THPVAR_BEGIN, $varcontent);
			$varcontent = str_replace(self::PATTERN_THPVAR_END, self::REPLACE_THPVAR_END, $varcontent);

			// Записать замененный контент, записать код PHP
			$result .= $varcontent;
			$result .= $splitmatch[$i];
		}
		// Обработать последний блок
		$varcontent = $this->replacePointToArray($splitdata[$splitcount]);
		$varcontent = str_replace(self::PATTERN_THPVAR_BEGIN, self::REPLACE_THPVAR_BEGIN, $varcontent);
		$varcontent = str_replace(self::PATTERN_THPVAR_END, self::REPLACE_THPVAR_END, $varcontent);

		// Записать замененный контент
		$result .= $varcontent;
		return $result;
	}
	
	/**
	 * Компилировать THP-like код
	 * Простая замена одних скобок на другие скобки
	 * 
	 * @param string $contents Код шаблона
	 * @return string Код шаблона
	 */
	private function compileThpLikeCode($contents)
	{
		$result = str_replace(self::PATTERN_THPLIKECODE_BEGIN, self::REPLACE_THPLIKECODE_BEGIN, $contents);
		return str_replace(self::PATTERN_THPLIKECODE_END, self::REPLACE_THPLIKECODE_END, $result);
	}
	
	/**
	 * Производит разбиение кода PHP для выявления блоков за скобками PHP
	 * Код PHP остается без изменений, код THP отправляется на преобразование
	 * 
	 * @param string $contents Код шаблона
	 * @return string Код шаблона
	 */
	private function replacePointToArray($contents)
	{
		$result = '';

		$splitcount = preg_match_all(self::PATTERN_PREG_THPVAR, $contents, $splitmatch);
		$splitdata = preg_split(self::PATTERN_PREG_THPVAR, $contents);
		$splitmatch = $splitmatch[1]; // Получим нужный блок

		for($i = 0; $i < $splitcount; ++$i)
		{
			$result_operator = $this->replacePointToArrayInternal($splitmatch[$i]);
			
			$result .= $splitdata[$i];
			$result .= '{'.$result_operator.'}';
		}
		$result .= $splitdata[$splitcount];
		
		return $result;
	}
	
	/**
	 * Преобразование переменной THP в PHP
	 * 
	 * @param string $contents Код шаблона
	 * @return string Код шаблона
	 */
	private function replacePointToArrayInternal($operator)
	{
		$operator_part = explode(self::ARRAY_DELIMETR, $operator);
		$count = count($operator_part);
		
		// Первый элемент оператора
		if(empty($operator_part[0]))
			$result = self::ARRAY_DEFAULT_FIRST_ELEMENT;
		else
		{
			$strlen_before = strlen($operator_part[0]);
			$trim_operator = ltrim($operator_part[0], '$');
			$strlen_after = strlen($trim_operator);
			
			if($strlen_before > $strlen_after)
			{
				$result = 'data->'.$trim_operator;
			}
			else
			{
				$result = $operator_part[0];
			}
		}
		
		for($i = 1; $i < $count; ++$i)
		{
			$obj_part = explode(self::PROP_DELIMETR, $operator_part[$i]);
			$obj_count = count($obj_part);
			
			$result .= "['$obj_part[0]']";
			for($n = 1; $n < $obj_count; ++$n)
			{
				$result .= '->'.$obj_part[$n];
			}
		}

		return $result;
	}
}