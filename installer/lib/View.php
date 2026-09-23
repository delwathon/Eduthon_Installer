<?php

namespace Eduthon\Installer;

final class View
{
    /**
     * Render a template inside the layout. Templates escape output with e().
     *
     * @param  array<string, mixed>  $data
     */
    public static function render(string $directory, string $template, array $data): string
    {
        $content = self::capture($directory.'/'.$template.'.php', $data);

        return self::capture($directory.'/layout.php', $data + ['content' => $content]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function partial(string $directory, string $template, array $data = []): string
    {
        return self::capture($directory.'/partials/'.$template.'.php', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
