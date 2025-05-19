<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;

class GenerateTravelClickMarkdown extends Command
{
    protected $signature = 'travelclick:markdown';
    protected $description = 'Generate Markdown documentation for all TravelClick classes';

    protected $basePath = 'app/TravelClick';
    protected $outputPath = 'docs/markdown';

    protected $namespaces = [
        'Builders' => 'XML Builders',
        'DTOs' => 'Data Transfer Objects',
        'Enums' => 'Enumerations',
        'Services' => 'Services',
        'Parsers' => 'Parsers',
        'Console' => 'Console Commands',
        'Events' => 'Events',
        'Exceptions' => 'Exceptions',
        'Http' => 'HTTP Components',
        'Jobs' => 'Jobs',
        'Listeners' => 'Event Listeners',
        'Models' => 'Models',
        'Observers' => 'Model Observers',
        'Rules' => 'Validation Rules',
        'Support' => 'Support Classes',
    ];

    public function handle()
    {
        $this->info('Generating TravelClick markdown documentation...');

        // Prepare output directory
        $this->prepareOutputDirectory();

        // Create index
        $this->createIndex();

        // Process all namespaces
        $this->processAllNamespaces();

        $this->info('Documentation generated successfully!');
        $this->info("Available at: {$this->outputPath}/README.md");

        return 0;
    }

    protected function prepareOutputDirectory()
    {
        if (!File::isDirectory($this->outputPath)) {
            File::makeDirectory($this->outputPath, 0755, true);
        }
    }

    protected function processAllNamespaces()
    {
        foreach ($this->namespaces as $namespace => $title) {
            $this->processNamespace($namespace, $title);
        }

        // Process any additional namespaces found in the directory
        $this->processAdditionalNamespaces();
    }

    protected function processAdditionalNamespaces()
    {
        $directories = File::directories(base_path($this->basePath));

        foreach ($directories as $directory) {
            $namespace = basename($directory);

            if (!array_key_exists($namespace, $this->namespaces)) {
                $this->processNamespace($namespace, $namespace);
                $this->namespaces[$namespace] = $namespace;
            }
        }
    }

    protected function processNamespace($namespace, $title)
    {
        $this->info("Processing {$title}...");

        $path = base_path("{$this->basePath}/{$namespace}");
        if (!File::isDirectory($path)) {
            $this->warn("Directory not found: {$path}");
            return;
        }

        // Create namespace directory
        $namespaceOutputPath = "{$this->outputPath}/{$namespace}";
        if (!File::isDirectory($namespaceOutputPath)) {
            File::makeDirectory($namespaceOutputPath, 0755, true);
        }

        $classes = $this->findClassesInNamespace($namespace, $path);

        if ($classes->isEmpty()) {
            $this->warn("No classes found in {$namespace}");
            return;
        }

        // Generate documentation for each class
        foreach ($classes as $class) {
            $this->generateClassFile($namespaceOutputPath, $class);
        }

        // Create namespace README
        $this->createNamespaceReadme($namespaceOutputPath, $title, $classes);
    }

    protected function findClassesInNamespace($namespace, $path)
    {
        return collect(File::allFiles($path))
            ->filter(function ($file) {
                return $file->getExtension() === 'php';
            })
            ->map(function ($file) use ($namespace) {
                $relativePath = $file->getRelativePathname();
                $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
                return "App\\TravelClick\\{$namespace}\\{$className}";
            })
            ->filter(function ($class) {
                try {
                    $filePath = $this->getClassFilePath($class);
                    if ($filePath && $this->isValidPhpFile($filePath)) {
                        return class_exists($class) || interface_exists($class) || trait_exists($class);
                    }
                    return false;
                } catch (\Throwable $e) {
                    $this->warn("Skipping invalid class file {$class}: {$e->getMessage()}");
                    return false;
                }
            });
    }

    protected function getClassFilePath($class)
    {
        $relativePath = str_replace(['App\\TravelClick\\', '\\'], ['', '/'], $class) . '.php';
        return base_path("{$this->basePath}/{$relativePath}");
    }

    protected function isValidPhpFile($filePath)
    {
        $content = file_get_contents($filePath);

        // Check for namespace declaration issues
        /*if (preg_match('/^\s*<\?php\s+\n+\s*namespace/m', $content)) {
            $this->warn("File has invalid namespace declaration (whitespace after opening tag): {$filePath}");
            return false;
        }*/

        return true;
    }

    protected function generateClassFile($namespaceOutputPath, $class)
    {
        try {
            $reflection = new ReflectionClass($class);
            $shortName = $reflection->getShortName();
            $fileName = "{$namespaceOutputPath}/{$shortName}.md";

            $markdown = $this->generateClassDocumentation($reflection);
            File::put($fileName, $markdown);

            $this->info("Generated documentation for {$class}");
        } catch (\Throwable $e) {
            $this->error("Error processing class {$class}: {$e->getMessage()}");
        }
    }

    protected function generateClassDocumentation(ReflectionClass $reflection)
    {
        $shortName = $reflection->getShortName();
        $markdown = "# {$shortName}\n\n";
        $markdown .= "**Full Class Name:** `{$reflection->getName()}`\n\n";
        $markdown .= "**File:** `{$this->getRelativeClassPath($reflection)}`\n\n";

        // Class type (class, interface, trait)
        $type = $this->getClassType($reflection);
        $markdown .= "**Type:** {$type}\n\n";

        // Class description
        $docComment = $reflection->getDocComment();
        if ($docComment) {
            $docLines = $this->parseDocComment($docComment);
            $markdown .= "## Description\n\n{$docLines['description']}\n\n";
        }

        // Methods
        $methods = $this->getClassMethods($reflection);
        if (!empty($methods)) {
            $markdown .= $this->generateMethodsDocumentation($methods);
        }

        return $markdown;
    }

    protected function getRelativeClassPath(ReflectionClass $reflection)
    {
        $classPath = str_replace('\\', '/', $reflection->getName());
        return str_replace('App/TravelClick/', '', $classPath) . '.php';
    }

    protected function getClassType(ReflectionClass $reflection)
    {
        if ($reflection->isInterface()) {
            return 'Interface';
        }

        if ($reflection->isTrait()) {
            return 'Trait';
        }

        return 'Class';
    }

    protected function getClassMethods(ReflectionClass $reflection)
    {
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        return array_filter($methods, function ($method) use ($reflection) {
            return $method->getDeclaringClass()->getName() === $reflection->getName();
        });
    }

    protected function generateMethodsDocumentation(array $methods)
    {
        $markdown = "## Methods\n\n";

        foreach ($methods as $method) {
            $methodDoc = $this->parseDocComment($method->getDocComment() ?: '');

            $markdown .= $this->generateMethodDocumentation($method, $methodDoc);
            $markdown .= "---\n\n";
        }

        return $markdown;
    }

    protected function generateMethodDocumentation(ReflectionMethod $method, array $methodDoc)
    {
        $markdown = "### `{$method->getName()}`\n\n";

        if (!empty($methodDoc['description'])) {
            $markdown .= "{$methodDoc['description']}\n\n";
        }

        // Method signature
        $markdown .= $this->generateMethodSignature($method);

        // Parameters documentation
        if (!empty($methodDoc['params'])) {
            $markdown .= $this->generateParametersDocumentation($methodDoc['params']);
        }

        // Return documentation
        if (!empty($methodDoc['return'])) {
            $markdown .= $this->generateReturnDocumentation($methodDoc['return']);
        }

        return $markdown;
    }

    protected function generateMethodSignature(ReflectionMethod $method)
    {
        $parameters = array_map(function ($param) {
            return $this->formatParameter($param);
        }, $method->getParameters());

        $returnTypeStr = $method->hasReturnType()
            ? ': ' . $this->formatType($method->getReturnType())
            : '';

        return "```php\npublic function {$method->getName()}(" . implode(', ', $parameters) . "){$returnTypeStr}\n```\n\n";
    }

    protected function formatParameter(\ReflectionParameter $param)
    {
        $typeStr = $param->hasType()
            ? $this->formatType($param->getType())
            : 'mixed';

        $default = $param->isDefaultValueAvailable()
            ? ' = ' . $this->formatDefaultValue($param->getDefaultValue())
            : '';

        return "{$typeStr} \${$param->getName()}{$default}";
    }

    protected function generateParametersDocumentation(array $params)
    {
        $markdown = "**Parameters:**\n\n";

        foreach ($params as $param) {
            $markdown .= "- `\${$param['name']}` ({$param['type']}): {$param['description']}\n";
        }

        return $markdown . "\n";
    }

    protected function generateReturnDocumentation(array $returnInfo)
    {
        return "**Returns:** {$returnInfo['type']} - {$returnInfo['description']}\n\n";
    }

    protected function formatType(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName() . ($type->allowsNull() && !$type->isBuiltin() ? '|null' : '');
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map([$this, 'formatType'], $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map([$this, 'formatType'], $type->getTypes()));
        }

        return 'mixed';
    }

    protected function formatDefaultValue($value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return empty($value) ? '[]' : '[...]';
        }

        if (is_string($value)) {
            return "'{$value}'";
        }

        return var_export($value, true);
    }

    protected function parseDocComment($docComment)
    {
        $result = [
            'description' => '',
            'params' => [],
            'return' => null,
        ];

        if (empty($docComment)) {
            return $result;
        }

        // Remove comment delimiters and stars
        $docComment = preg_replace('/^\s*\/\*\*|\*\/\s*$/s', '', $docComment);
        $docComment = preg_replace('/^\s*\*\s?/m', '', $docComment);

        // Split into lines
        $lines = explode("\n", $docComment);

        $descriptionLines = [];
        $inDescription = true;

        foreach ($lines as $line) {
            $line = trim($line);

            // If line starts with @, it's a tag
            if (strpos($line, '@') === 0) {
                $inDescription = false;
                $this->parseDocTag($line, $result);
            } elseif ($inDescription && !empty($line)) {
                $descriptionLines[] = $line;
            }
        }

        $result['description'] = implode("\n", $descriptionLines);

        return $result;
    }

    protected function parseDocTag($line, &$result)
    {
        // Parse @param tag
        if (preg_match('/@param\s+([^\s]+)\s+\$([^\s]+)\s*(.*)/', $line, $matches)) {
            $result['params'][] = [
                'type' => $matches[1],
                'name' => $matches[2],
                'description' => $matches[3],
            ];
        }

        // Parse @return tag
        if (preg_match('/@return\s+([^\s]+)\s*(.*)/', $line, $matches)) {
            $result['return'] = [
                'type' => $matches[1],
                'description' => $matches[2],
            ];
        }
    }

    protected function createNamespaceReadme($namespaceOutputPath, $title, $classes)
    {
        $markdown = "# {$title}\n\n";
        $markdown .= "## Classes\n\n";

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $shortName = $reflection->getShortName();
            $markdown .= "- [{$shortName}]({$shortName}.md)\n";
        }

        File::put("{$namespaceOutputPath}/README.md", $markdown);
    }

    protected function createIndex()
    {
        $markdown = "# TravelClick Integration Documentation\n\n";
        $markdown .= "Documentation for all TravelClick integration classes.\n\n";

        $markdown .= "## Namespaces\n\n";

        foreach ($this->namespaces as $namespace => $title) {
            $markdown .= "- [{$title}]({$namespace}/README.md)\n";
        }

        File::put("{$this->outputPath}/README.md", $markdown);
    }
}
