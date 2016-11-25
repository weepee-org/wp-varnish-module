vcl 4.0;
import std;

backend default {
  .host = "127.0.0.1";
  .port = "8000";
  .first_byte_timeout = 60s;
  .connect_timeout = 300s;
}
 
acl purge {
  "localhost";
  "127.0.0.1";
}

sub vcl_backend_response {
  set beresp.http.X-Url = bereq.url;
}

sub vcl_deliver {
  unset resp.http.X-Url;
  unset resp.http.X-Content-Tags;
}


sub vcl_recv {

if (req.method == "PURGE") {

    if (!client.ip ~ purge) {
        return (synth(405,"Not allowed."));
    }

    if(!req.http.X-Content-Tags && !req.http.X-Url-To-Ban && !req.http.X-Purge-All){
	return (synth(400, "Content tags or url header required"));
    }

    if(req.http.X-Content-Tags) {
	ban("obj.http.X-Content-Tags ~ " + req.http.X-Content-Tags);
    } elseif(req.http.X-Url-To-Ban) {
	ban("obj.http.X-Url ~ " + req.http.X-Url-To-Ban);
    }
    else {
	ban("req.url ~ /");
    }
    
    return (synth(200, "purged"));
}

set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_[_a-z]+|has_js)=[^;]*", "");
set req.http.Cookie = regsub(req.http.Cookie, "^;\s*", "");

if (req.url ~ "/feed(/)?") {
	return ( pass ); 
}

if (req.http.Accept-Encoding) {
    if (req.url ~ "\.(jpg|png|gif|gz|tgz|bz2|tbz|mp3|ogg)$") {
      unset req.http.Accept-Encoding;
    } elsif (req.http.Accept-Encoding ~ "gzip") {
      set req.http.Accept-Encoding = "gzip";
    } elsif (req.http.Accept-Encoding ~ "deflate") {
      set req.http.Accept-Encoding = "deflate";
    } else {
      unset req.http.Accept-Encoding;
    }
  }

  if (req.method != "GET" &&
    req.method != "HEAD" &&
    req.method != "PUT" && 
    req.method != "POST" &&
    req.method != "TRACE" &&
    req.method != "OPTIONS" &&
    req.method != "DELETE") {
      return (pipe);
  }
   
  if (req.method != "GET" && req.method != "HEAD") {
    return (pass);
  }
  
  if ( req.http.cookie ~ "wordpress_logged_in" ) {
    return( pass );
  }
  
  if (!(req.url ~ "wp-(login|admin)") 
    && !(req.url ~ "&preview=true" ) 
  ){
    unset req.http.cookie;
  }

  if (req.http.Authorization || req.http.Cookie) {
    return (pass);
  }
  
  return (hash);
}

sub vcl_backend_response {
  set beresp.http.Vary = "Accept-Encoding";
  if (!(bereq.url ~ "wp-(login|admin)") && !bereq.http.cookie ~ "wordpress_logged_in" ) {
    unset beresp.http.set-cookie;
    set beresp.ttl = 52w;
  }

  if (beresp.ttl <= 0s ||
    beresp.http.Set-Cookie ||
    beresp.http.Vary == "*") {
      set beresp.ttl = 120 s;
      set beresp.uncacheable = true;
      return (deliver);
  }

  return (deliver);
}
