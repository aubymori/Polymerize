# Polymerize
Polymerize is a custom YouTube server that aims to faithfully restore the desktop Polymer YouTube
frontend from December of 2021.

## ⚠️ NOTICE ⚠️
Polymerize is meant for advanced users that actually know what they are doing. When encountering a
problem, you should know:

- How to troubleshoot it and how to determine if it is an issue on your side
- How to report it properly, if you cannot fix it

Support will not be provided to users who cannot think for themselves.
<!-- ^ tfw r/oldyoutubelayout -->

## Installation
Installation is mostly similar to [Rehike](https://github.com/Rehike/Rehike), though Polymerize has
its own certificates. The Rehike certificates will work, but the Polymerize certificates have an
easy-to-access generation script `ssl/sslgen.php` if you need to generate new certificates.

## Configuration
There is no configuration GUI, at least for now. You can make the file `config.json` to configure
Polymerize, and you can view `modules/Polymerize/ConfigDefinitions.php` for the configuration
definitions.

You can also override the experiment flags stored in the `yt.config_.EXPERIMENT_FLAGS` JSON object
by creating the file `expflags_overrides.json`. See `data/ytcfg_base.json` for the default values
of each experiment flag.

You can also override the player experiment flags by creating the `player_flags_overrides.txt` file.
Flags are defined in a `key=value` format, separated by newlines. For example:

```
expflag1=true
expflag2=false
```

See `data/player_flags_base.txt` for the default values of each player flag.