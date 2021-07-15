HISTSIZE=5000
HISTFILESIZE=5000
alias ls="ls --color=auto"
alias grep="grep --color=auto"
alias diff="diff --color=auto"
export LESS_TERMCAP_md=$'\e[1;38;2;200;0;0m'
export LESS_TERMCAP_me=$'\e[0m'
export LESS_TERMCAP_se=$'\e[0m'
export LESS_TERMCAP_so=$'\e[7;38;2;0;0;200m'
export LESS_TERMCAP_ue=$'\e[0m'
export LESS_TERMCAP_us=$'\e[4;38;2;0;200;0m'
stty stop ^F
PS1='[\u@\h \w]\$ '
alias ll="ls -l"
alias la="ls -a"
alias l=ls
alias lla="ls -la"
alias ee="echo -e"
alias en="echo -n"
alias e=echo
alias een="echo -en"
alias ip="ip --color=auto"
alias n=nano
alias h=history
alias c=cat
alias g="grep -E"
alias s="sed -E"
tabs 4
export TERM="xterm-256color"
