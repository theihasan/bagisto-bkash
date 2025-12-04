<?php

namespace Ihasan\Bkash\DTO;

readonly abstract class BaseDTO
{
    /**
     * Create DTO from array data
     */
    public static function fromArray(array $data): static
    {
        return new static(...static::mapData($data));
    }

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getConstructor()?->getParameters() ?? [];
        
        $result = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $result[$name] = $this->{$name};
        }
        
        return $result;
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Map input data to constructor parameters
     */
    protected static function mapData(array $data): array
    {
        return $data;
    }
}