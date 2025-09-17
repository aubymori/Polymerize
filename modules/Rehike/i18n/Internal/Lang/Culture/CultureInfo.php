<?php
namespace Rehike\i18n\Internal\Lang\Culture;

/**
 * Describes cultural information about a given language.
 * 
 * @author Taniko Yamamoto <kirasicecreamm@gmail.com>
 */
class CultureInfo
{
    /**
     * The display name of the language.
     * 
     * For example, "English" or "日本語".
     */
    public string $languageName;

    /**
     * The expanded display name of the language, if applicable.
     * 
     * For example, "English (United States)".
     */
    public ?string $expandedLanguageName = null;

    /**
     * The base language ID, if applicable.
     * 
     * For example, en-GB may extend from en.
     */
    public ?string $baseLanguageId = null;

    /**
     * The writing direction of the language.
     * 
     * @var WritingDirection
     */
    public int $writingDirection = WritingDirection::LTR;

    /**
     * The language's standard thousands separator.
     * 
     * For example, English speakers will use "32,000" and German speakers will
     * use "32.000".
     */
    public string $thousandsSeparator = ",";

    /**
     * The language's standard decimal separator.
     * 
     * For example, English speakers will use "12.5" and German speakers will
     * use "12,5".
     */
    public string $decimalSeparator = ".";

    /**
     * Whether or not this language abbreviates numbers.
     * 
     * For example, German does not abbreviate numbers and will always display full counts.
     */
    public bool $abbreviateNumbers = true;

    /**
     * The target amount of digits to be displayed in abbreviated numbers.
     * 
     * For example with two digits in English:
     *   1,234       -> 1.2K
     *   12,345      -> 12K
     *   123,456     -> 123K
     *   1,234,567   -> 1.2M
     *   12,345,678  -> 12M
     *   123,456,789 -> 123M
     */
    public int $abbreviatedDigitCount = 2;

    /**
     * Number abbreviation formats, where the key
     * is the minimum value, and the value is the string format
     * to use for that minimum value.
     */
    public array $numberAbbreviations = [
        1000          => "%sK",
        1000000       => "%sM",
        1000000000    => "%sB",
        1000000000000 => "%sT"
    ];

    /**
     * Whether or not the language should place a line break when parsing
     * multiline strings.
     * 
     * For example, English and most other languages that use the Latin script
     * should do this, but languages like Chinese or Japanese should not.
     */
    public bool $spaceOnLineBreak = false;

    /**
     * The culture's date/time information.
     */
    public CultureDateTimeInfo $dateTimeInfo;
}