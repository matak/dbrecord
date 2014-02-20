<?php

namespace dbrecord;


/**
 * This should be free because of memory leaks
 */
interface IObjectContainerToFree
{
	function free();
	
}
