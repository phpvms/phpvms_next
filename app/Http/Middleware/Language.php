<?php

namespace App\Http\Middleware;

use Closure;

class Language
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
		// check if 'lang' is part of request
		if ($request->has('lang')) {
			$locale = $request->get('lang'); 

			return $this->setLang($request, $locale);
		}
		
		// get locale from session or cookie and change that
		if(\Session::has('locale')) {
			$this->setLang($request, \Session::get('locale'));
		} elseif ($locale = $request->cookie('language')) {
			$this->setLang($request, $locale);  
		}
		return $next($request);
	}
	
	private function setLang($request, $locale) 
	{
		if (file_exists(resource_path("lang/$locale"))) {
			$path   = $request->path();
			
			// make a cookie
			\Cookie::queue(\Cookie::make(
				'language', $locale, 60*24*365 // 365 days 
			));
			
			// save in session
			\Session::put('locale', $locale);

			// change App locale
			\App::setLocale($locale); 

			// redirect with cookie
			return redirect($path)->withCookie(cookie('language'));			
		} else {
			
			// make a flash error to show on view
			flash('Could not find requested language!', 'danger');
			return redirect($request->path());
		}
	}
}
