<?php

// Include for swagger documentation
use OpenApi\Annotations as OA;

///////////////////////////////
// SWAGGER API DOCUMENTATION //
///////////////////////////////
/**
 * @OA\Info(title="Email generator API", version="1.0")
 */

/**
 * @OA\Post(
 *     path="/api/v1.0/email-generator",
 *     summary="Email generator",
 *     description="Generate an email based on input parameters and a query named expression",
 *     @OA\RequestBody(
 *     	   description="JSON Object containing the input strings and the query expression to consider for the email generation"
 *     	   required=true,
 *     	   @OA\MediaType(
 *     	   	mediaType="application/json",                 
 *              @OA\Schema(ref="#/components/schemas/SchemaInputs")
 *         )
 *     ),
 *     OA\Response(
 *         response=200,
 *         description="Success",
 *     @OA\Schema(ref="#/components/schemas/SearchResultObject)   
 *     ), 
 *     @OA\Response(
 *         response=404,
 *         description="Could Not Find Resource"
 *     )   
 * )
 */

//////////////
// ROUTINES //
//////////////
/* Splitting the query expression into chunks based on the tild and parenthesis characters.
 * @param[in] queryExp The query named expression to process to dynamically generate the email.
 * @param[in] input The array containing the input parameters to consider for the email generation.
 * @return string - The email generated based on the query and the parameters.
 */
function processQueryExpConcatenations($queryExp, $input) : string
{
    // Splitting the expression into chunks based on the tilds	
    $queryExpressions = array_map('trim', explode("~", $queryExp));
    $tabIndex = array();

    // Conditions with concatenations may have been splitted into pieces so
    // gathering them before the processing of the condition
    for ($index = 0; $index < count($queryExpressions); $index++)
    {
        if (($queryExpressions[$index][0] == "(") &&
            (strpos($queryExpressions[$index], "?") !== false) &&
            (strpos($queryExpressions[$index], ":") !== true) )
        {
            array_push($tabIndex, $index);
        }
        else if (($queryExpressions[$index][-1] == ")") &&
                 (strpos($queryExpressions[$index], ":") !== false) &&
                 ($queryExpressions[$index][0] != "("))
        {
            array_push($tabIndex, $index);
        }
    }
    for ($index = 0; $index < count($tabIndex); $index += 2)
    {
        $startIndex = $tabIndex[$index];
        $endIndex = $tabIndex[$index + 1];
        $startArray = array_slice($queryExpressions, 0, ($startIndex - 1));
        $mergeConditionStr = join(' ~ ',array_slice($queryExpressions, $startIndex, ($endIndex - 1)));
        $mergeArray = array($mergeConditionStr);
        $endArray = array_slice($queryExpressions, ($endIndex + 1));
        $queryExpressions = array_merge($startArray, $mergeArray , $endArray);
    }

    // Iterating on all the expressions of the query to process them
    $result = "";
    foreach ($queryExpressions as $expression)
    {
        $expressionResult = processExpression($expression, $input);
        $result = $result . $expressionResult;
    }
    return $result;
}

/* Main routine to process the various parts of the query expression.
 * @param[in] expression The isolated expression from the query to process.
 * @param[in] input The input arguments to consider.
 * @return The result of the processing performed on the input expression.
 */ 
function processExpression($expression, $input)
{
    // Numeric expression
    if ( is_numeric($expression) )
    {
        return intval($expression);
    }
    // Conditional expression
    else if (preg_match_all("@^\(([^?:]+)\?([^?:]+):([^?:]*)\)$@", $expression, $matches, PREG_SET_ORDER) > 0)
    {
	// Trim the spaces and processing the ternary condition
        $matches = array_map('trim', $matches[0]);
        if (preg_match("@^([^!=><]+)\s*(==|===|!=|<>|!==|>|<|>=|<=)\s*([^!=><]+)\s*$@", trim($matches[1]), $conditionMatches) > 0)
	{
	    // Processing the conditional expression to check if it is verified or not 
            $conditionMatches = array_map('trim', $conditionMatches);
            $leftResult = processExpression($conditionMatches[1], $input);
            $rightResult = processExpression($conditionMatches[3], $input);
            $conditionResult = evaluateCondition($leftResult, $conditionMatches[2], $rightResult);

	    // Condition is verified
	    if ($conditionResult)
	    {
		// Case where we have a concatenation to process
                if ( strpos($matches[2], "~") !== false)
                {
                    return processQueryExpConcatenations($matches[2], $input);
		}
		// No concatenation to process in the condition
                else
                {
                    return processExpression($matches[2], $input);
                }
	    }
	    // Condition is not verified
            else
	    {
		// Case where we have a concatenation to process
                if ( strpos($matches[3], "~") !== false)
                {
                    return processQueryExpConcatenations($matches[3], $input);
		}
		// No concatenation to process in the condition
                else
                {
                    return processExpression($matches[3], $input);
                }
            }
	}
	// Invalid condition or condition not processed correctly
        else
        {
            throw new Exception("Condition is invalid or incorrectly processed", 1);
        }
    }
    // Single input to evaluate
    else if (preg_match("@^input(\d+)$@", $expression, $matches) > 0)
    {
	$indexInput = intval( $matches[1] );
        return ($indexInput <= count($input)) ? $input[$indexInput - 1] : throw new Exception("Invalid input present in the query expression", 2);
    }
    // Input requiring some operations (might be nested)
    else if (preg_match_all("@^input(\d+)(\.([a-zA-Z]+)\(-?\d*\))+$@", $expression, $matches, PREG_SET_ORDER) > 0)
    {
	// Iterating on the various operations to perform on the input
        $indexInput = intval( $matches[0][1] );
        if ($indexInput >= count($input)) return "";
        $fullExpression = $matches[0][0];
        $expressionsToApply = array_slice(explode(".", $fullExpression), 1);
        $result = $input[$indexInput - 1];
        foreach ($expressionsToApply as $exp)
	{
            // Operation with an input argument inputX.operation(arg)
            if (preg_match("@([a-zA-Z]+)\((-?\d+)\)$@", $exp, $expMatches) > 0)
            {
                $expMethod = $expMatches[1];
                $expParameter = $expMatches[2];
                $result = executeFunction($result, $expMethod, $expParameter);
	    }
	    // Operation without any input argument inputX.operation()
            else if (preg_match("@([a-zA-Z]+)\(\)$@", $exp, $expMatches) > 0)
            {
                $expMethod = $expMatches[1];
                $result = executeFunctionNoParam($result, $expMethod);
	    }
	    // Invalid operation performed on the input or incorrectly managed
            else
	    {
                throw new Exception("Invalid operations performed on the input", 3);
            }
        }
        return $result;
    }
    // String processing with one or multiple characters
    // /!\ Case with special characters(~ or ? or :) inside is not managed
    else if (preg_match("@^'(.*)'$@", $expression, $matches) > 0)
    {
        return $matches[1];
    }
    // Query expression is invalid or incorrectly processed
    else
    {
        throw new Exception("Invalid expression or expression incorrectly managed", 4);
    }
}

/* Execution of an operation with an argument on the input.
 * @param[in] input The input on which we need to perform the operation.
 * @param[in] function The function/operation to perform on the input.
 * @param[in] inputParam The input parameter of the function to apply on the input.
 * @return string - The string result of the operation performed on the input.
 */ 
function executeFunction($input, $function, $inputParam) : string
{
    return ($function)($input, $inputParam);
}

/* Execution of an operation with an argument on the input.
 * @param[in] input The input on which we need to perform the operation.
 * @param[in] function The function/operation to perform on the input.
 * @param[in] inputParam The input parameter of the function to apply on the input.
 * @return string - The string result of the operation performed on the input.
 */
function executeFunctionNoParam($input, $function) : int
{
    return ($function)($input);
}

/* Function evaluating a condition with the different comparison operators managed.
 * @param[in] left The left part of the comparison.
 * @param[in] operator The operator to consider for the condition.
 * @param[in] right The right part of the comparison.
 * @return bool The boolean result of the condition.
 * @throws Operator in the condition is not managed.
 */
function evaluateCondition($left, $operator, $right) : bool
{
    switch ($operator) {
    case "==":
    case "===":
        return $left === $right;
    case "!=":
    case "!==":
    case "<>":
        return $left !== $right;
    case ">":
        return $left > $right;
    case "<":
        return $left < $right;
    case ">=":
        return $left >= $right;
    case "<=":
        return $left <= $right;
    default:
        throw new Exception("Operator in the condition is not managed", 5);
    }
}

//function composeFunction($input, $function, $inputParam)
//{
//    $input = is_array($input) ? join(" ", $input) : $input;
//    return executeFunction($input, $function, $inputParam);
//}

//////////////////////////
// OPERATIONS AVAILABLE //
//////////////////////////
/* Take the N first characters in lowercase of each word in the input.
 * @param[in] input The input to consider.
 * @param[in] charCount The number (N) of characters to take at the beginning of each word.
 * @return string - The result string after this function.
 */
function eachWordFirstChars($input, $charCount) : string
{
    $wordSplit = preg_split("/[\s,-]+/", strtolower($input));
    $firstChars = array();
    $indexWord = 0;
    foreach($wordSplit as $word){
        $firstChars[$indexWord] = substr($word, 0, $charCount);
        $indexWord++;
    }
    return join('', $firstChars);
}

/* Count the number of words in the input.
 * @param[in] input The input on which to count the words.
 * @return int - The count of words in the input.
 */
function wordsCount($input) : int
{
    return str_word_count($input);
}

/* Take the last N words in the input.
 * @param[in] input The input to consider.
 * @param[in] wordCount The count (N) of words to take from the end of the input.
 * @return string - The result string.
 */
function lastWords($input, $wordCount) : string
{
    $wordSplit = preg_split("/[\s,-]+/", strtolower($input));
    for ($index = 0; $index < abs($wordCount); $index++)
    {
        ($wordCount > 0) ? array_shift($wordSplit) : array_pop($wordSplit);
    }
    return join(' ', $wordSplit);
}

////////////
// CHECKS //
////////////
/* Check for invalid characters in the generated email which are filtered.
 * @param[in] email The generated email on which we have to filter prohibited characters.
 * @return string The result email after the filtering of prohibited characters.
 */
function emailFilterProhibitedChars($email) : string
{
    $emailChars = str_split($email);
    $emailChars = array_filter($emailChars, "filterInvalidChars");
    return str_replace(' ', '', join('',$emailChars));
}

/* Predicate on the validity of a character among the email characters.
 * @param[in] var The input character to test as a valid email character.
 * @return bool - Is the email character valid or not.
 */
function filterInvalidChars($var) : bool
{
    return (preg_match("/^\s|\w|@|-|./", $var) > 0);
}

/* Main routine to call to generate an email based on the input parameters and query expression.
 * @param[in] inputQueryExpression The query expression to consider for the email generation.
 * @param[in] inputArguments The input parameters to consider for the email generation.
 * @return array - Array containing the email generated from the user input parameters and expression.
 */
function generateEmail($inputQueryExpression, $inputArguments) : array
{
    $id = processQueryExpConcatenations($inputQueryExpression, $inputArguments);
    $id = emailFilterProhibitedChars($id);
    $data = [ 'id' => $id, 'value' => $id ];
    return $data;
}

//////////
// MAIN //
//////////
function main() : string
{
    // Takes raw data from the request
    $requestBodyStr = file_get_contents('php://input');

    // Converts it into a PHP object
    $requestBody = json_decode($requestBodyStr);

    // Return the generated email based on the input parameters and the query expression
    return json_encode(generateEmail($requestBody->queryExpression, $requestBody->inputs), JSON_PRETTY_PRINT);
}

?>
<html><body>
<pre id="json"><?php echo main(); ?></pre>
</body></html>
