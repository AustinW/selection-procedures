<?php

if (! function_exists('float_compare')) {
    /**
     * Compare two arbitrary precision numbers
     *
     * @param  string|float|int  $num1  Left operand
     * @param  string  $operand  Operator: <, <=, >, >=, =, ==, ===, !=
     * @param  string|float|int  $num2  Right operand
     * @param  int  $precision  Optional precision (decimal places)
     * @return bool|null Null if operator is invalid
     */
    function float_compare(
        string|float|int $num1,
        string $operand,
        string|float|int $num2,
        int $precision = 3
    ): ?bool {
        // Ensure operands are strings for bccomp
        $num1 = (string) $num1;
        $num2 = (string) $num2;

        return match ($operand) {
            '<' => bccomp($num1, $num2, $precision) === -1,
            '<=' => bccomp($num1, $num2, $precision) !== 1,
            '>' => bccomp($num1, $num2, $precision) === 1,
            '>=' => bccomp($num1, $num2, $precision) !== -1,
            '=', '==', '===' => bccomp($num1, $num2, $precision) === 0,
            '!=' => bccomp($num1, $num2, $precision) !== 0,
            default => null,
        };
    }
}
