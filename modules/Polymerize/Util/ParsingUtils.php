<?php
namespace Polymerize\Util;

use function count;
use function array_key_last;
use function preg_replace;

/**
 * Provides common InnerTube parsing utilities for Polymerize.
 * 
 * @author aubymori <aubyomori@gmail.com>
 */
class ParsingUtils
{
    /**
     * Get the text content of an InnerTube API response field.
     */
    public static function getText(mixed $source): ?string
    {
        // Determine the source type and act accordingly.
        if (is_object($source))
        {
            /*
             * InnerTube has two main text types:
             * 
             *   - runs: Fragments of formatted text separated into an
             *           array for further parsing. Runs should typically
             *           be parsed contextually or with a different method
             *           during templating, rather than by this function.
             * 
             *   - simpleText: Unformatted raw strings.
             * 
             * Rarely, these are even used interchangeably. It just helps
             * to have a single function that can handle both cases and
             * return a single, unformatted string.
             */
            if (isset($source->runs))
            {
                $response = "";

                foreach ($source->runs as $run)
                {
                    $response .= $run->text;
                }

                return $response;
            }
            else if (isset($source->simpleText))
            {
                return $source->simpleText;
            }
            else if (isset($source->content))
            {
                return self::getText(self::attributedStringToFormattedString($source));
            }
        }
        else if (is_string($source))
        {
            return $source;
        }
        
        // If no text is found, return null so that further code can
        // handle error cases.
        return null;
    }

    /**
     * Convert an indexed runs object to the older runs format.
     * 
     * We use mb_substr with UTF-8 here, because the indices are set up for a JS environment.
     * 
     * @param object $attributedString     Object containing indexed runs data
     * @param bool   $ignoreStyles         Ignore any styles and only parse commands.
     * 
     * @return ?object
     */
    public static function attributedStringToFormattedString(object $attributedString, bool $ignoreStyles = false): ?object
    {
        // If there's no styleRuns data
        if (!isset($attributedString->styleRuns) && !isset($attributedString->commandRuns))
        {
            return (object) [
                "runs" => [
                    (object) [
                        "text" => $attributedString->content
                    ]
                ]
            ];
        }

        $runs = [];
        $commandRuns = @$attributedString->commandRuns ?? [];
        $styleRuns = @$attributedString->styleRuns ?? null;
        if ($ignoreStyles)
            $styleRuns = null;

        $previousStart = 0;
        $previousLength = 0;

        $textLength = 0;

        foreach ($styleRuns ?? $commandRuns as $irun)
        {
            if (!isset($irun->startIndex) || !isset($irun->length))
                continue;

            $text = self::mb_substr_ex($attributedString->content, $irun->startIndex, $irun->length);
            $run = (object)[
                "text" => $text
            ];
            $textLength += mb_strlen($text);

            if (isset($styleRuns))
            {
                $commandRun = null;
                foreach ($commandRuns as $crun)
                {
                    if ($crun->length == $irun->length && $crun->startIndex == $irun->startIndex)
                    {
                        $commandRun = $crun;
                        break;
                    }
                }
            }
            else
            {
                $commandRun = $irun;
            }

            // WHY WHY WHY WHY WHY WHY WHY
            if (isset($irun->fontName))
            switch($irun->fontName)
            {
                case "Roboto-Medium":
                    $run->bold = true;
                    break;
                case "Roboto-Italic":
                    $run->italics = true;
                    break;
                case "Roboto-Medium-Italic":
                    $run->bold = true;
                    $run->italics = true;
                    break;
            }

            if (@$irun->strikethrough == "LINE_STYLE_SINGLE")
            {
                $run->strikethrough = true;
            }

            if (@$irun->italic)
            {
                $run->italics = true;
            }

            if (@$irun->weightLabel == "FONT_WEIGHT_MEDIUM")
            {
                $run->bold = true;
            }

            $endpoint = null;
            if ($endpoint = @$commandRun->onTap->innertubeCommand)
            {
                $run->navigationEndpoint = $endpoint;
            }

            if ($previousStart + $previousLength < $irun->startIndex)
            {
                $start = 0; $length = 0;
                if ($previousStart == 0 && $previousLength == 0)
                {
                    $start = 0;
                    $length = $irun->startIndex;
                }
                else
                {
                    $start = $previousStart + $previousLength;
                    $length = $irun->startIndex - $start;
                }

                $text = self::mb_substr_ex(
                    $attributedString->content,
                    $start, $length
                );

                $runs[] = (object)[
                    "text" => $text
                ];
                $textLength += mb_strlen($text);
            }

            $runs[] = $run;

            $previousStart = $irun->startIndex;
            $previousLength = @$irun->length;
        }

        if (mb_strlen($attributedString->content) > $textLength)
        {
            $runs[] = (object)[
                "text" => self::mb_substr_ex(
                    $attributedString->content,
                    $textLength,
                    mb_strlen($attributedString->content) - $textLength
                )
            ];
        }

        return (object) [
            "runs" => $runs
        ];
    }

    /**
     * Custom mb_substr function for attributedStringToFormattedString.
     * The default mb_substr will cause breakage with emojis.
     * 
     * @see   attributedStringToFormattedString()
     * 
     * @param string $str     String to crop.
     * @param int    $offset  Zero-indexed offset to begin cropping at.
     * @param ?int   $length  Length of the cropped string.
     * 
     * @return string
     */
    public static function mb_substr_ex(string $str, int $offset, ?int $length): string {
        $bmp = [];
        for($i = 0; $i < mb_strlen($str); $i++)
        {
            $mb_substr = mb_substr($str, $i, 1);
            $mb_ord = mb_ord($mb_substr);
            $bmp[] = $mb_substr;
            if ($mb_ord > 0xFFFF)
            {
                $bmp[] = "";
            }
        }
        return implode("", array_slice($bmp, $offset, $length));
    }
}