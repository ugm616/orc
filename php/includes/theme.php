<?php
// Theme system for PHP implementation

declare(strict_types=1);

class Theme {
    public array $colors;
    public array $typography;
    public array $layout;
    public array $header;
    public array $footer;
    
    public function __construct(array $data = []) {
        $this->colors = $data['colors'] ?? [];
        $this->typography = $data['typography'] ?? [];
        $this->layout = $data['layout'] ?? [];
        $this->header = $data['header'] ?? [];
        $this->footer = $data['footer'] ?? [];
    }
    
    public static function getDefault(): Theme {
        return new self([
            'colors' => [
                'primary' => '#6366f1',
                'secondary' => '#8b5cf6',
                'background' => '#0f172a',
                'surface' => '#1e293b',
                'text' => '#f8fafc',
                'textMuted' => '#94a3b8',
                'border' => '#334155',
                'success' => '#10b981',
                'warning' => '#f59e0b',
                'error' => '#ef4444'
            ],
            'typography' => [
                'fontFamily' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                'fontSize' => '16px',
                'lineHeight' => '1.6'
            ],
            'layout' => [
                'maxWidth' => '800px',
                'spacing' => '1rem',
                'borderRadius' => '0.5rem',
                'shadowBase' => '0 1px 3px 0 rgba(0, 0, 0, 0.1)',
                'shadowLg' => '0 10px 15px -3px rgba(0, 0, 0, 0.1)'
            ],
            'header' => [
                'showLogo' => false,
                'logoPath' => '',
                'title' => 'Orc Social',
                'subtitle' => 'Privacy-First Social Network',
                'layout' => 'center'
            ],
            'footer' => [
                'text' => 'Powered by Orc Social',
                'links' => [
                    ['text' => 'Privacy', 'url' => '/privacy'],
                    ['text' => 'Terms', 'url' => '/terms']
                ]
            ]
        ]);
    }
    
    public static function load(string $path): Theme {
        if (!file_exists($path)) {
            $theme = self::getDefault();
            $theme->save($path);
            return $theme;
        }
        
        $data = json_decode(file_get_contents($path), true);
        if (!$data) {
            return self::getDefault();
        }
        
        return new self($data);
    }
    
    public function save(string $path): bool {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = [
            'colors' => $this->colors,
            'typography' => $this->typography,
            'layout' => $this->layout,
            'header' => $this->header,
            'footer' => $this->footer
        ];
        
        return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
    
    public function generateCSS(): string {
        $css = ":root {\n";
        
        // Colors
        foreach ($this->colors as $key => $value) {
            $cssKey = $this->camelToKebab($key);
            $css .= "  --color-{$cssKey}: {$value};\n";
        }
        
        // Typography
        foreach ($this->typography as $key => $value) {
            $cssKey = $this->camelToKebab($key);
            $css .= "  --{$cssKey}: {$value};\n";
        }
        
        // Layout
        foreach ($this->layout as $key => $value) {
            $cssKey = $this->camelToKebab($key);
            $css .= "  --{$cssKey}: {$value};\n";
        }
        
        $css .= "}\n";
        
        return $css;
    }
    
    private function camelToKebab(string $string): string {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }
    
    public function validate(): ?string {
        // Validate colors
        foreach ($this->colors as $key => $color) {
            if (!$this->isValidColor($color)) {
                return "Invalid color: {$key} = {$color}";
            }
        }
        
        // Validate header layout
        $validLayouts = ['center', 'left', 'right'];
        if (!in_array($this->header['layout'] ?? '', $validLayouts)) {
            return "Invalid header layout: " . ($this->header['layout'] ?? 'empty');
        }
        
        return null; // Valid
    }
    
    private function isValidColor(string $color): bool {
        if (empty($color)) {
            return false;
        }
        
        // Check hex colors
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return true;
        }
        
        // Allow rgb() and rgba() functions
        if (preg_match('/^rgba?\([^)]+\)$/', $color)) {
            return true;
        }
        
        // Allow common CSS color names
        $cssColors = [
            'black', 'white', 'red', 'green', 'blue', 'yellow',
            'cyan', 'magenta', 'gray', 'grey', 'transparent'
        ];
        
        return in_array(strtolower($color), $cssColors);
    }
    
    public function toArray(): array {
        return [
            'colors' => $this->colors,
            'typography' => $this->typography,
            'layout' => $this->layout,
            'header' => $this->header,
            'footer' => $this->footer
        ];
    }
}
?>