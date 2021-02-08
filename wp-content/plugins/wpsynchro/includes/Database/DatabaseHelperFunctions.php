<?php
namespace WPSynchro\Database;

/**
 * Database helper functions
 * @since 1.6.0
 */
class DatabaseHelperFunctions
{

    /**
     *  Handle table prefix name changes, if needed
     *  @since 1.3.2
     */
    public static function handleTablePrefixChange($table_name, $source_prefix, $target_prefix)
    {

        // Check if we need to change prefixes
        if ($source_prefix != $target_prefix) {
            if (substr($table_name, 0, strlen($source_prefix)) == $source_prefix) {
                $table_name = substr($table_name, strlen($source_prefix));
                $table_name = $target_prefix . $table_name;
            }
        }
        return $table_name;
    }

    /**
     *  Check if specific table is being moved, by search for table name ends with X
     *  @since 1.6.0
     */
    public static function isTableBeingTransferred($tablelist, $table_prefix, $table_ends_with)
    {
        foreach ($tablelist as $table) {    
            $tablename_with_prefix = str_replace($table_prefix,"",$table->name);
           if($tablename_with_prefix === $table_ends_with) {
               return true;
           }
        }
        return false;
    }
}