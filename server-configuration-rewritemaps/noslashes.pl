#!/usr/bin/perl
$| = 1;

while (<>) {
    
    if ($_ eq '/') {
        print "/index";
        exit;
    }
    

    #trim: verwijder slashes op begin en eind:
    $_ =~ s/(?:^\/|\/$)//g;
    
    #vervang slashes door koppeltekens:
    $_ =~ s/\//-/g;
    
    print "/" . $_;
}
