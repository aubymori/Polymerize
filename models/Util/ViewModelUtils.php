<?php
namespace Polymerize\Model\Util;

class ViewModelUtils
{
    public static function findEntity(string $key, object $frameworkUpdates): ?object
    {
        foreach ($frameworkUpdates->entityBatchUpdate->mutations as $mutation)
        {
            if ($mutation->entityKey == $key)
            {
                return $mutation->payload;
            }
        }
        return null;
    }

    public static function findEntities(array $keys, object $frameworkUpdates): object
    {
        $result = (object)[];
        foreach ($keys as $name => $key)
        {
            if (!is_null($key))
                $result->{$name} = self::findEntity($key, $frameworkUpdates);
        }
        return $result;
    }
}