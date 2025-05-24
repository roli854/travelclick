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
use ReflectionProperty;
use ReflectionClassConstant;

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
        'Facades' => 'Facades',
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

        // Create namespace README with complete API
        $this->createNamespaceReadmeWithAPI($namespaceOutputPath, $namespace, $title, $classes);
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
                        // Check if the class/interface/trait exists
                        return class_exists($class) || interface_exists($class) || trait_exists($class) ||
                            (function_exists('enum_exists') && enum_exists($class));
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

        // Class type (class, interface, trait, enum)
        $type = $this->getClassType($reflection);
        $markdown .= "**Type:** {$type}\n\n";

        // Class description
        $docComment = $reflection->getDocComment();
        if ($docComment) {
            $docLines = $this->parseDocComment($docComment);
            $markdown .= "## Description\n\n{$docLines['description']}\n\n";
        }

        // Constants
        $constants = $this->getClassConstants($reflection);
        if (!empty($constants)) {
            $markdown .= $this->generateConstantsDocumentation($constants);
        }

        // Properties
        $properties = $this->getClassProperties($reflection);
        if (!empty($properties)) {
            $markdown .= $this->generatePropertiesDocumentation($properties);
        }

        // Methods
        $methods = $this->getClassMethods($reflection);
        if (!empty($methods)) {
            $markdown .= $this->generateMethodsDocumentation($methods);
        }

        return $markdown;
    }

    protected function createNamespaceReadmeWithAPI($namespaceOutputPath, $namespace, $title, $classes)
    {
        $markdown = "# {$title}\n\n";
        $markdown .= "## Overview\n\n";
        $markdown .= "This namespace contains " . count($classes) . " classes/interfaces/enums.\n\n";

        // Table of Contents
        $markdown .= "## Table of Contents\n\n";
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $shortName = $reflection->getShortName();
            $type = $this->getClassType($reflection);
            $markdown .= "- [{$shortName}](#{$this->slugify($shortName)}) ({$type})\n";
        }
        $markdown .= "\n";

        // Complete API Reference
        $markdown .= "## Complete API Reference\n\n";

        foreach ($classes as $class) {
            try {
                $reflection = new ReflectionClass($class);
                $markdown .= $this->generateClassAPISection($reflection);
            } catch (\Throwable $e) {
                $this->error("Error generating API for {$class}: {$e->getMessage()}");
            }
        }

        // Individual class links
        $markdown .= "## Detailed Documentation\n\n";
        $markdown .= "For detailed documentation of each class, see:\n\n";
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $shortName = $reflection->getShortName();
            $markdown .= "- [{$shortName}]({$shortName}.md)\n";
        }

        File::put("{$namespaceOutputPath}/README.md", $markdown);
    }

    protected function generateClassAPISection(ReflectionClass $reflection): string
    {
        $shortName = $reflection->getShortName();
        $type = $this->getClassType($reflection);

        $markdown = "---\n\n";
        $markdown .= "### {$shortName}\n\n";
        $markdown .= "**Type:** {$type}\n";
        $markdown .= "**Full Name:** `{$reflection->getName()}`\n\n";

        // Brief description from docblock
        $docComment = $reflection->getDocComment();
        if ($docComment) {
            $docLines = $this->parseDocComment($docComment);
            if (!empty($docLines['description'])) {
                $firstLine = explode("\n", $docLines['description'])[0];
                $markdown .= "**Description:** {$firstLine}\n\n";
            }
        }

        // If it's an enum, show cases
        if ($this->isEnum($reflection)) {
            $markdown .= $this->generateEnumCasesAPI($reflection);
        }

        // Constants
        $constants = $this->getClassConstants($reflection);
        if (!empty($constants)) {
            $markdown .= "#### Constants\n\n";
            $markdown .= "```php\n";
            foreach ($constants as $constant) {
                $markdown .= $this->formatConstantSignature($constant) . "\n";
            }
            $markdown .= "```\n\n";
        }

        // Properties
        $properties = $this->getClassProperties($reflection);
        if (!empty($properties)) {
            $markdown .= "#### Properties\n\n";
            $markdown .= "```php\n";
            foreach ($properties as $property) {
                $markdown .= $this->formatPropertySignature($property) . "\n";
            }
            $markdown .= "```\n\n";
        }

        // Methods
        $methods = $this->getClassMethods($reflection);
        if (!empty($methods)) {
            $markdown .= "#### Methods\n\n";
            $markdown .= "```php\n";
            foreach ($methods as $method) {
                $markdown .= $this->formatMethodSignatureForAPI($method) . "\n";
            }
            $markdown .= "```\n\n";
        }

        return $markdown;
    }

    protected function isEnum(ReflectionClass $reflection): bool
    {
        // Check if the class has the isEnum method (PHP 8.1+)
        if (method_exists($reflection, 'isEnum')) {
            return $reflection->isEnum();
        }

        // For older PHP versions, check if it's in the Enums namespace
        return str_contains($reflection->getName(), '\\Enums\\');
    }

    protected function generateEnumCasesAPI(ReflectionClass $reflection): string
    {
        $markdown = "#### Enum Cases\n\n";
        $markdown .= "```php\n";

        // Try to get enum cases if the method exists
        if (method_exists($reflection, 'getCases')) {
            try {
                $cases = $reflection->getCases();
                foreach ($cases as $case) {
                    if (method_exists($case, 'getBackingValue')) {
                        $value = $case->getBackingValue();
                        $valueStr = is_string($value) ? "'{$value}'" : $value;
                        $markdown .= "case {$case->getName()} = {$valueStr};\n";
                    } else {
                        $markdown .= "case {$case->getName()};\n";
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to constants for enum-like classes
                $this->generateEnumConstantsAsAPI($reflection, $markdown);
            }
        } else {
            // For older PHP versions or enum-like classes, use constants
            $this->generateEnumConstantsAsAPI($reflection, $markdown);
        }

        $markdown .= "```\n\n";
        return $markdown;
    }

    protected function generateEnumConstantsAsAPI(ReflectionClass $reflection, &$markdown): void
    {
        $constants = $reflection->getConstants();
        foreach ($constants as $name => $value) {
            // Skip magic constants
            if (in_array($name, ['class'])) continue;

            $valueStr = is_string($value) ? "'{$value}'" : $value;
            $markdown .= "const {$name} = {$valueStr};\n";
        }
    }

    protected function formatConstantSignature(ReflectionClassConstant $constant): string
    {
        $visibility = $this->getConstantVisibility($constant);
        $value = $this->formatConstantValue($constant->getValue());
        return "{$visibility} const {$constant->getName()} = {$value};";
    }

    protected function formatPropertySignature(ReflectionProperty $property): string
    {
        $visibility = $this->getPropertyVisibility($property);
        $static = $property->isStatic() ? 'static ' : '';
        $readonly = '';

        // Check if isReadOnly method exists (PHP 8.1+)
        if (method_exists($property, 'isReadOnly') && $property->isReadOnly()) {
            $readonly = 'readonly ';
        }

        $typeStr = '';
        if ($property->hasType()) {
            $typeStr = $this->formatType($property->getType()) . ' ';
        }

        return "{$visibility} {$static}{$readonly}{$typeStr}\${$property->getName()};";
    }

    protected function formatMethodSignatureForAPI(ReflectionMethod $method): string
    {
        $visibility = $this->getMethodVisibility($method);
        $static = $method->isStatic() ? 'static ' : '';
        $abstract = $method->isAbstract() ? 'abstract ' : '';
        $final = $method->isFinal() ? 'final ' : '';

        $parameters = array_map(function ($param) {
            return $this->formatParameter($param);
        }, $method->getParameters());

        $returnTypeStr = $method->hasReturnType()
            ? ': ' . $this->formatType($method->getReturnType())
            : '';

        return "{$visibility} {$static}{$abstract}{$final}function {$method->getName()}(" .
            implode(', ', $parameters) . "){$returnTypeStr};";
    }

    protected function getConstantVisibility(ReflectionClassConstant $constant): string
    {
        if ($constant->isPublic()) return 'public';
        if ($constant->isProtected()) return 'protected';
        if ($constant->isPrivate()) return 'private';
        return 'public';
    }

    protected function getPropertyVisibility(ReflectionProperty $property): string
    {
        if ($property->isPublic()) return 'public';
        if ($property->isProtected()) return 'protected';
        if ($property->isPrivate()) return 'private';
        return 'public';
    }

    protected function getMethodVisibility(ReflectionMethod $method): string
    {
        if ($method->isPublic()) return 'public';
        if ($method->isProtected()) return 'protected';
        if ($method->isPrivate()) return 'private';
        return 'public';
    }

    protected function formatConstantValue($value): string
    {
        if ($value === null) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_string($value)) return "'{$value}'";
        if (is_array($value)) {
            if (empty($value)) return '[]';
            // For simple arrays, show the values
            if (count($value) <= 3 && !$this->isAssociativeArray($value)) {
                $items = array_map([$this, 'formatConstantValue'], $value);
                return '[' . implode(', ', $items) . ']';
            }
            return '[...]';
        }
        return var_export($value, true);
    }

    protected function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function slugify(string $text): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $text));
    }

    protected function getClassConstants(ReflectionClass $reflection): array
    {
        return array_filter(
            $reflection->getReflectionConstants(),
            fn($constant) => $constant->isPublic() &&
                $constant->getDeclaringClass()->getName() === $reflection->getName()
        );
    }

    protected function getClassProperties(ReflectionClass $reflection): array
    {
        return array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            fn($property) => $property->getDeclaringClass()->getName() === $reflection->getName()
        );
    }

    protected function generateConstantsDocumentation(array $constants): string
    {
        $markdown = "## Constants\n\n";

        foreach ($constants as $constant) {
            $markdown .= "### `{$constant->getName()}`\n\n";

            $docComment = $constant->getDocComment();
            if ($docComment) {
                $docLines = $this->parseDocComment($docComment);
                if (!empty($docLines['description'])) {
                    $markdown .= "{$docLines['description']}\n\n";
                }
            }

            $value = $this->formatConstantValue($constant->getValue());
            $markdown .= "**Value:** `{$value}`\n\n";
            $markdown .= "---\n\n";
        }

        return $markdown;
    }

    protected function generatePropertiesDocumentation(array $properties): string
    {
        $markdown = "## Properties\n\n";

        foreach ($properties as $property) {
            $markdown .= "### `\${$property->getName()}`\n\n";

            $docComment = $property->getDocComment();
            if ($docComment) {
                $docLines = $this->parseDocComment($docComment);
                if (!empty($docLines['description'])) {
                    $markdown .= "{$docLines['description']}\n\n";
                }
            }

            if ($property->hasType()) {
                $type = $this->formatType($property->getType());
                $markdown .= "**Type:** `{$type}`\n\n";
            }

            $markdown .= "---\n\n";
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

        if ($this->isEnum($reflection)) {
            return 'Enum';
        }

        if ($reflection->isAbstract()) {
            return 'Abstract Class';
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
            ? $this->formatType($param->getType()) . ' '
            : '';

        $default = $param->isDefaultValueAvailable()
            ? ' = ' . $this->formatDefaultValue($param->getDefaultValue())
            : '';

        $variadic = $param->isVariadic() ? '...' : '';

        return "{$typeStr}{$variadic}\${$param->getName()}{$default}";
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

    protected function formatType(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            // Handle built-in types
            if ($type->isBuiltin()) {
                return $name . ($type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '|null' : '');
            }

            // Handle class types
            $className = $name;
            if (class_exists($className) || interface_exists($className) || trait_exists($className)) {
                // Use short name if it's in the same namespace
                if (str_starts_with($className, 'App\\TravelClick\\')) {
                    $parts = explode('\\', $className);
                    $className = end($parts);
                }
            }

            return $className . ($type->allowsNull() ? '|null' : '');
        }

        if ($type instanceof ReflectionUnionType) {
            $types = array_map(function ($t) {
                return $t instanceof ReflectionNamedType ? $t->getName() : 'mixed';
            }, $type->getTypes());
            return implode('|', $types);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $types = array_map(function ($t) {
                return $t instanceof ReflectionNamedType ? $t->getName() : 'mixed';
            }, $type->getTypes());
            return implode('&', $types);
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
