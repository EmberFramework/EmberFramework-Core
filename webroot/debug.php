<?php


if(isset($_GET['disabled']))
{
	echo 'Debugging Disabled<br/>';
	Debug::disable();
	echo '<a href="/debug">Enable Debugging</a><br/><a href="/">Home</a>';
}
else
{
	echo 'Debugging Enabled<br/>';
	Debug::enable ();

	foreach(Debug::getAvalableOptions() as $o)
		Debug::setOption($o);

	echo '<a href="/debug?disabled">Disable Debugging</a><br/><a href="/">Home</a>';
}


Session::finalize();