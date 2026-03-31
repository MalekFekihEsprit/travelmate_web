import re

input_file = "Version20260331132923.php"
output_file = "Version20260331132923.php"

seen = set()
cleaned_lines = []

with open(input_file, "r", encoding="utf-8") as f:
    lines = f.readlines()

for line in lines:
    stripped = line.strip()

    # Only target addSql lines
    if stripped.startswith("$this->addSql"):
        if stripped not in seen:
            seen.add(stripped)
            cleaned_lines.append(line)
        else:
            print(f"Removed duplicate: {stripped}")
    else:
        cleaned_lines.append(line)

with open(output_file, "w", encoding="utf-8") as f:
    f.writelines(cleaned_lines)

print("✅ Cleaning done. Output file:", output_file)