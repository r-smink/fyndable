#!/usr/bin/perl
# Rebuild a WordPress plugin zip from a plugin directory, using forward-slash
# entry names (PowerShell Compress-Archive writes backslashes which break
# installs on Linux). Usage: perl tools/make-zip.pl <zip> <dir>
use strict;
use warnings;
use File::Find;
use IO::Compress::Zip qw(zip $ZipError);

my ($zipFile, $root) = @ARGV;
die "usage: make-zip.pl <zip> <dir>\n" unless $zipFile && $root;

my @files;
find(sub {
    return unless -f $_;
    my $name = $File::Find::name;
    $name =~ s{\\}{/}g;
    push @files, $name;
}, $root);

unlink $zipFile;
zip \@files => $zipFile, CanonicalName => 1
    or die "zip failed: $ZipError\n";

printf "wrote %s (%d files)\n", $zipFile, scalar @files;
