#!/bin/bash

OP_DIR=$(dirname $0)
"$OP_DIR/test-cli-deps.sh"

# Positions to choose from
positions=(
  "Prop"
  "Hooker"
  "Lock"
  "Flanker"
  "Number 8"
  "Scrum-half"
  "Fly-half"
  "Centre"
  "Winger"
  "Fullback"
)

# Strengths templates
strengths=(
  "A powerful and determined runner who creates space and supports the attack."
  "Reads the game well and adapts quickly to changing situations."
  "Tackles fearlessly and consistently disrupts opposition plays."
  "Strong communicator and always willing to lead by example."
  "A well-rounded player with a high work rate and strong technical skills."
)

# Areas for development templates
dev_items=(
  "Improve acceleration and top-end speed for breakaways"
  "Develop a more consistent passing game under pressure"
  "Enhance understanding of defensive structures"
  "Focus on positional discipline in open play"
  "Increase vocal presence during key phases"
  "Work on tackling technique to reduce missed tackles"
  "Build strength for better impact in contact situations"
)

# Generate random date in range
random_dob() {
  start="2007-09-01"
  end="2010-08-31"
  start_ts=$(date -d "$start" +%s)
  end_ts=$(date -d "$end" +%s)
  random_ts=$(shuf -i $start_ts-$end_ts -n 1)
  date -d "@$random_ts" +"%Y/%m/%d"
}

# Generate random name (very basic)
random_name() {
  first_names=(Henry Jack Tom Leo Oliver Sam Ben Max Alfie Noah Charlie Ethan George Isaac Lucas Reuben Theo Harvey Nathan Zachary Alex Oscar Liam Freddie Finn Sebastian Toby Caleb Jayden Rory)
  last_names=(Smith Jones Taylor Brown Wilson Johnson White Harris Martin Cooper Anderson Bailey Brooks Chapman Davies Dixon Edwards Foster Gibson Hayes Lawson Mason Nash Palmer Reid Stevens Turner Walsh Young Webb)
  echo "${first_names[$RANDOM % ${#first_names[@]}]} ${last_names[$RANDOM % ${#last_names[@]}]}"
}

# Output file
output="players.json"
echo "{" > $output

for i in $(seq 1 60); do
  player_id=$(shuf -i 1000000-9999999 -n 1)
  dob=$(random_dob)
  name=$(random_name)

  # Get up to 3 unique random positions
  num_positions=$(shuf -i 0-3 -n 1)
  selected_positions=$(shuf -e "${positions[@]}" -n $num_positions | jq -R . | jq -s .)

  # Pick a random strength and 1–3 areas for development
  strength=$(shuf -e "${strengths[@]}" -n 1)
  dev_list=$(shuf -e "${dev_items[@]}" -n $(shuf -i 1-3 -n 1) | jq -R . | jq -s .)

  echo "  \"$player_id\": {" >> $output
  echo "    \"$dob\": {" >> $output
  echo "      \"name\": \"$name\"," >> $output
  echo "      \"dob\": \"$dob\"," >> $output
  echo "      \"positions\": $selected_positions," >> $output
  echo "      \"strengths\": \"$strength\"," >> $output
  echo "      \"areas_for_development\": $dev_list" >> $output
  echo -n "    }" >> $output
  if [ "$i" -lt 60 ]; then
    echo -e "\n  }," >> $output
  else
    echo -e "\n  }" >> $output
  fi
done

echo "}" >> $output
echo "Generated 60 players in $output"
