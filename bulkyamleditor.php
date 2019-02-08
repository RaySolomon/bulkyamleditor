<?php
/**
 * @package Bulk Yaml Editor
 * @license GNU General Public License version 3 or later; see LICENSE
 * @author  Aetherweb
 */

// This script trawls for PHP-FPM YAML files and edits their content en-mass.
// You can specify values you wish to add/edit, values you wish to remove and
// users to exclude/include. Run it at the command line as root with:
//
// php bulkyamleditor.php

/////////////////////////////////////////////////////
// So, what do you want to do?

// Do not run this without testing first! Switch $LIVE to true when happy
$LIVE = false;

// list of keys and vals to add (or edit if exists) (required)
$edits   = array("php_admin_value[disable_functions]" => "show_source,system,shell_exec,passthru,exec,popen,proc_open,phpinfo,ini_set,curl_exec");

// list of keys to remove if exist (optional)
$removes = array("pm_max_children");

// Any exclusions? list of account names (or parts of names!) to exclude (optional)
$excludes = array("nobody","alice","bob");

// Any inclusions? If you want to only affect accounts matching some pattern
// put them here. If this is empty, ALL but excludes will be affected (optional)
$includes = array();
/////////////////////////////////////////////////////


//////////////////////////////////////////////
// Enumerate ALL account's YAML files...
$base = "/var/cpanel/userdata/";
//////////////////////////////////////////////

// Traverse base directory looking for YAMLs
$files = dirList($base);
foreach ($files as $file)
{
	echo $file . "\n";

	//////////////////////////////////////////////////////////
	// Are we attempting to process this directory?
	$DOTHISONE = true;

	if ((sizeof($excludes) > 0) and (preg_match("/".join("|",$excludes)."/i", $file)))
	{
		echo " EXCLUDED BY EXCLUDES\n";
		$DOTHISONE = false;
	}

	if ((sizeof($includes) > 0) and (!preg_match("/".join("|",$includes)."/i", $file)))
	{
		echo " EXCLUDED BY INCLUDES\n";
		$DOTHISONE = false;
	}

	if ($DOTHISONE)
	{
		$subfiles = dirList($base . $file . "/", "/.*\.yaml$/");
		foreach ($subfiles as $subfile)
		{
			$filename = $base . $file . "/" . $subfile;
			echo " " . $filename . "\n";

			// Determine what lines are already in there...
			$linestr = file_get_contents($base . $file ."/" . $subfile);
			$lines = explode("\n", $linestr);

			////////////////////////////////////////////////////
			// Begin to form the new lines to replace the old
			// Start with what MUST be there.
			$newlines = array();
			$newlines[] = "---"; // must have this - seems to have a space on the end in production files. Unsure if absolutely necessary.
			$newlines[] = "_is_present: 1"; // and this
			////////////////////////////////////////////////////

			////////////////////////////////////////////////////
			// Stage 1
			// Traverse existing lines ($lines) and edit/keep
			foreach ($lines as $line)
			{
				// clean up $line to ensure no repeated spaces
				// which would then mean they don't match 
				$line = preg_replace("/\s+/", " ", $line);
				$line = strtolower(trim($line));

				if (strlen($line) > 0) // if not a blank line
				{
					if (in_array($line, $newlines))
					{
						// Skip it, already there.
					}
					else
					{
						// Split it into it's name and value by a colon
						list($name, $value) = explode(":", $line);

						// we found a line, do we have an edit for it?
						// or a remove, or...
						if (in_array($name, array_keys($edits)))
						{
							// edit and add to newlines
							$newlines[] = trim(strtolower($name)) . ": " . trim(strtolower($edits[$name]));
						}
						elseif (in_array($name, $removes))
						{
							// do not include this line
						}
						else
						{
							// not edited, added or removed so copy it over
							$newlines[] = $line;
						}
					}
				} // end of if not a blank line
			} // End of Stage 1
			////////////////////////////////////////////////////

			////////////////////////////////////////////////////
			// Stage 2
			// Traverse edits and make sure they're present
			foreach ($edits as $name => $value)
			{
				// just in cases
				$name = strtolower(trim($name));
				$value = strtolower(trim($value));

				if (strlen($name) > 0)
				{
					// form the line this would be
					$line = $name . ": " . $value;

					if (in_array($line, $newlines))
					{
						// It's already there
					} 
					else
					{
						// It's not there so add it
						$newlines[] = $line;
					}
				}
			} // End of Stage 2
			////////////////////////////////////////////////////


			////////////////////////////////////////////////////
			// Join newlines into a complete file content block
			// Production files seem to have a \n on the end
			// And for peace of mind, let's add a space on the end
			// of the --- line...
			$newlines[0] = "--- ";
			$newcontent = join($newlines, "\n") . "\n";

			if ($newcontent <> $linestr)
			{
				// OK we have a changed file, let's write it out...
				echo "FILE CHANGED FROM\n\n";
				echo $linestr . "\n\nTO\n\n";
				echo $newcontent . "\n\n";

				if ($LIVE)
				{
					if (file_put_contents($filename, $newcontent))
					{
						echo "WRITTEN TO FILE!\n\n";
					}
					else
					{
						echo "\033[31mPROBLEM WRITING TO $filename\033[37m\n\n";
					}
				}
				else
				{
					echo "\033[31mNOT LIVE. Would be writing out to: " . $filename . "\033[37m\n\n";
				}
			}
			else
			{
				echo "FILE CONTENT NOT CHANGED\n";
			}
			////////////////////////////////////////////////////

			// Move on to the next file in this directory...
		} // end of subfiles loop
	} // end of if DOTHISONE 
	echo "\n";
} // end of directories loop

echo "All done. You must now rebuild your PHP-FPM YAML files by running:\n\n";
echo "/scripts/php_fpm_config --rebuild\n\n";
echo "And then restarting the PHP-FPM service.\n\n";


function dirList ($directory, $pattern = '')
{

    // create an array to hold directory list
    $results = array();

    // create a handler for the directory
    $handler = opendir($directory);

    // keep going until all files in directory have been read
    while ($file = readdir($handler)) {

        // if $file isn't this directory or its parent,
        // add it to the results array
        if ($file != '.' && $file != '..')
		{
			if (($pattern == '') or (preg_match($pattern, $file)))
			{
                $results[] = $file;
			}
		}
    }

    // tidy up: close the handler
    closedir($handler);

    // done!
    return $results;

}

