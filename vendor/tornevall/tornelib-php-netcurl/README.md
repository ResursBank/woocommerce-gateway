# Tornevall Networks NETCURL library

[Full documents are located here](https://docs.tornevall.net/x/KwCy)


## Are we rebuilding the wheel here?

Well. Yes. Almost. The other libraries out there, are probably doing the exact same job as we do here. The **problem** with other libraries (that I've found) is amongst others that they are way too big. Taking for example GuzzleHTTP, is for example a huge project if you're aiming to use a smaller project that not requires tons of files to run. They probably covers a bit more solutions than this project, however, we are aiming to make curl usable on as many places as possible in a smaller format. What we are doing here is turning the curl PHP libraries into a very verbose state and with that, returning completed and parsed data to your PHP applications in a way where you don't have to think of this yourself. 


## Compatibility

This library should be compatible with at least PHP 5.3 up to PHP 7.2 (RC1)
