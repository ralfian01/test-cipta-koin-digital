<?php

namespace App\Http\Libraries;

use Illuminate\Http\UploadedFile;

class FormDataParser
{
    /** Function to parse non-file form-data to associative array
     * @return array
     */
    public static function parseFormData(string $formData = null)
    {
        if ($formData == null) return [];
        $parsedData = [];

        // Get boundary data
        $boundary = substr($formData, 0, strpos($formData, "\r\n"));

        // Get block data
        $blocks = array_slice(explode($boundary, $formData), 1);

        // Start parsing form-fata
        foreach ($blocks as $block) {

            // If $block is empty then skip to next array item
            if ($block == "--\r\n" || $block == "\r\n") continue;

            // Parses headers and content from a section
            list($key, $content) = explode("\r\n\r\n", $block, 2);
            preg_match('/name="([^"]+)"(?:\s*;[^"]+)*\s*$/i', $key, $matches);

            // Remove line breaks from string content
            $content = preg_replace("/\r|\n|\r\n/", "", $content);

            if ($matches) {

                $keyName = $matches[1]; // Get the original key name of form-data
                $parsedData[$keyName] = $content; // Push into $parsedArray as an associative array
            }
        }

        return $parsedData;
    }

    /** 
     * Function to parse file form-data
     * @return array
     */
    public static function parseFormDataFile(string $formData = null)
    {
        if ($formData == null) return [];
        $outputFiles = [];

        // Get boundary data
        $boundary = substr($formData, 0, strpos($formData, "\r\n"));

        // Get block data
        $blocks = array_slice(explode($boundary, $formData), 1);

        // Start parsing form-data file
        foreach ($blocks as $block) {

            // If $block is empty then skip to next array item
            if ($block == "--\r\n" || $block == "\r\n") continue;

            // Parses headers and content from a section
            list($rawHead, $body) = explode("\r\n\r\n", $block, 2);
            $rawHead .= "\r\n";

            // Parse raw head list
            $headers = [];

            preg_match_all('/([a-zA-Z0-9_-]+):\s*([^\r\n]+)\r\n/', $rawHead, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $headers[strtolower($match[1])] = $match[2];
            }

            // Checks whether the section contains file data or plain data 
            if (isset($headers['content-disposition'])) {

                preg_match('/name="([^"]+)"(; filename="([^"]+)")?/', $headers['content-disposition'], $nameMatches);
                $name = $nameMatches[1];

                // If the section contains files, save the file information
                if (isset($nameMatches[3])) {

                    $filename = $nameMatches[3];
                    $tempPath = tempnam(sys_get_temp_dir(), 'formdata');
                    file_put_contents($tempPath, $body);

                    // Check payload name is array or not
                    if (str_contains($name, '[') && substr($name, -1) == ']') {

                        $name = substr($name, 0, -1);
                        $name = explode('[', $name)[0];

                        if (isset($outputFiles[$name]) && !is_array($outputFiles[$name])) $outputFiles[$name] = [];

                        $outputFiles[$name][] = new UploadedFile(
                            $tempPath,
                            $filename,
                            $headers['content-type'],
                            UPLOAD_ERR_OK,
                            true
                        );
                    } else {

                        if (isset($outputFiles[$name]) && !is_object($outputFiles[$name])) $outputFiles[$name] = '';
                        $outputFiles[$name] = new UploadedFile(
                            $tempPath,
                            $filename,
                            $headers['content-type'],
                            UPLOAD_ERR_OK,
                            true
                        );
                    }
                }
            }
        }

        return $outputFiles;
    }
}
