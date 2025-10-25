#!/bin/bash

# Site Testing Script for Theory of Conspiracies
# Tests all pages for CSS being displayed as text content
# Usage: ./test_all_pages.sh

set -e

# Configuration
BASE_URL="http://localhost:8080"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RESULTS_DIR="/workspaces/stlouisintegration.com/testing/results_${TIMESTAMP}"
REPORT_FILE="${RESULTS_DIR}/test_report.txt"

# Create results directory
mkdir -p "$RESULTS_DIR"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Initialize counters
TOTAL_PAGES=0
PAGES_WITH_CSS_ISSUES=0
PAGES_CLEAN=0

echo -e "${BLUE}=== Theory of Conspiracies Site Testing ===${NC}"
echo "Testing all pages for CSS display issues..."
echo "Results will be saved to: $RESULTS_DIR"
echo ""

# Function to test a single page
test_page() {
    local url="$1"
    local page_name="$2"
    local endpoint="$3"
    
    TOTAL_PAGES=$((TOTAL_PAGES + 1))
    
    echo -e "${BLUE}Testing:${NC} $page_name ($endpoint)"
    
    # Test if page loads
    if ! curl -s --max-time 10 "$url" > "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null; then
        echo -e "${RED}  ❌ FAILED TO LOAD${NC}"
        echo "FAILED TO LOAD: $page_name ($endpoint)" >> "$REPORT_FILE"
        return 1
    fi
    
    # Check for CSS being displayed as text
    css_text_count=$(grep -c -i "font-family:\|background:\|color:\|margin:\|padding:" "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null || echo "0")
    
    # Check for style tags in content (another indicator of CSS issues)
    style_tag_count=$(grep -c "<style>" "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null || echo "0")
    
    # Extract any CSS text being displayed
    if [ "$css_text_count" -gt 0 ]; then
        grep -i "font-family:\|background:\|color:\|margin:\|padding:" "${RESULTS_DIR}/${page_name}_content.html" | head -10 > "${RESULTS_DIR}/${page_name}_css_issues.txt"
    fi
    
    # Check for HTTP errors
    http_status=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)
    
    # Determine page status
    if [ "$http_status" -ne 200 ]; then
        echo -e "${RED}  ❌ HTTP ERROR: $http_status${NC}"
        echo "HTTP ERROR $http_status: $page_name ($endpoint)" >> "$REPORT_FILE"
    elif [ "$css_text_count" -gt 5 ] || [ "$style_tag_count" -gt 0 ]; then
        PAGES_WITH_CSS_ISSUES=$((PAGES_WITH_CSS_ISSUES + 1))
        echo -e "${YELLOW}  ⚠️  CSS ISSUES DETECTED: $css_text_count CSS properties as text, $style_tag_count style tags${NC}"
        echo "CSS ISSUES: $page_name ($endpoint) - $css_text_count CSS properties, $style_tag_count style tags" >> "$REPORT_FILE"
    else
        PAGES_CLEAN=$((PAGES_CLEAN + 1))
        echo -e "${GREEN}  ✅ CLEAN${NC}"
        echo "CLEAN: $page_name ($endpoint)" >> "$REPORT_FILE"
    fi
    
    echo ""
}

# Initialize report
echo "=== THEORY OF CONSPIRACIES SITE TEST REPORT ===" > "$REPORT_FILE"
echo "Generated: $(date)" >> "$REPORT_FILE"
echo "Base URL: $BASE_URL" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Test all main pages
echo -e "${YELLOW}Testing Main Pages...${NC}"
test_page "$BASE_URL/" "Home Page" "/"
test_page "$BASE_URL/characters" "Characters Overview" "/characters"
test_page "$BASE_URL/story/act-i" "Act I Story" "/story/act-i"
test_page "$BASE_URL/story/act-ii" "Act II Story" "/story/act-ii"
test_page "$BASE_URL/setting/philadelphia-2085" "Philadelphia 2085 Setting" "/setting/philadelphia-2085"

echo -e "${YELLOW}Testing Character Pages...${NC}"
test_page "$BASE_URL/characters/sal-mueller" "Sal Mueller" "/characters/sal-mueller"
test_page "$BASE_URL/characters/tiger-mueller" "Tiger Mueller" "/characters/tiger-mueller"
test_page "$BASE_URL/characters/estella-mueller" "Estella Mueller" "/characters/estella-mueller"
test_page "$BASE_URL/characters/maria-santos" "Maria Santos" "/characters/maria-santos"
test_page "$BASE_URL/characters/keith-ai" "Keith AI" "/characters/keith-ai"
test_page "$BASE_URL/characters/iris-vasquez" "Iris Vasquez" "/characters/iris-vasquez"

echo -e "${YELLOW}Testing Story Sequence Pages...${NC}"
test_page "$BASE_URL/story/sequence-01" "Sequence 01" "/story/sequence-01"
test_page "$BASE_URL/story/sequence-02" "Sequence 02" "/story/sequence-02"
test_page "$BASE_URL/story/sequence-03" "Sequence 03" "/story/sequence-03"
test_page "$BASE_URL/story/sequence-04" "Sequence 04" "/story/sequence-04"
test_page "$BASE_URL/story/sequence-05" "Sequence 05" "/story/sequence-05"
test_page "$BASE_URL/story/sequence-06" "Sequence 06" "/story/sequence-06"
test_page "$BASE_URL/story/sequence-07" "Sequence 07" "/story/sequence-07"
test_page "$BASE_URL/story/sequence-08" "Sequence 08" "/story/sequence-08"
test_page "$BASE_URL/story/sequence-09" "Sequence 09" "/story/sequence-09"
test_page "$BASE_URL/story/sequence-10" "Sequence 10" "/story/sequence-10"

echo -e "${YELLOW}Testing Legal Pages...${NC}"
test_page "$BASE_URL/legal/terms-of-use" "Terms of Use" "/legal/terms-of-use"
test_page "$BASE_URL/legal/privacy-policy" "Privacy Policy" "/legal/privacy-policy"
test_page "$BASE_URL/legal/copyright" "Copyright" "/legal/copyright"
test_page "$BASE_URL/legal/disclaimers" "Disclaimers" "/legal/disclaimers"
test_page "$BASE_URL/legal/content-rating" "Content Rating" "/legal/content-rating"
test_page "$BASE_URL/legal/contact" "Contact" "/legal/contact"

# Generate summary
echo "" >> "$REPORT_FILE"
echo "=== SUMMARY ===" >> "$REPORT_FILE"
echo "Total Pages Tested: $TOTAL_PAGES" >> "$REPORT_FILE"
echo "Pages Clean: $PAGES_CLEAN" >> "$REPORT_FILE"
echo "Pages with CSS Issues: $PAGES_WITH_CSS_ISSUES" >> "$REPORT_FILE"
echo "Success Rate: $(( (PAGES_CLEAN * 100) / TOTAL_PAGES ))%" >> "$REPORT_FILE"

# Check controller library attachments
echo -e "${BLUE}=== CONTROLLER LIBRARY CHECK ===${NC}"
echo "=== CONTROLLER LIBRARY CHECK ===" >> "$REPORT_FILE"

CONTROLLERS_DIR="/workspaces/stlouisintegration.com/sites/theoryofconspiracies/web/modules/custom/theory_content/src/Controller"
CONTROLLERS_WITHOUT_SITE_LIB=0

if [ -d "$CONTROLLERS_DIR" ]; then
    for controller_file in "$CONTROLLERS_DIR"/*.php; do
        if [ -f "$controller_file" ]; then
            controller_name=$(basename "$controller_file" .php)
            
            # Check if controller has methods that return render arrays but don't attach site library
            has_return_array=$(grep -c "return \[" "$controller_file" 2>/dev/null || echo "0")
            has_site_lib=$(grep -c "theory_content/site" "$controller_file" 2>/dev/null || echo "0")
            
            if [ $has_return_array -gt 0 ] && [ $has_site_lib -eq 0 ]; then
                echo -e "  ${YELLOW}⚠️  $controller_name: Missing site library attachment${NC}"
                echo "MISSING SITE LIBRARY: $controller_name" >> "$REPORT_FILE"
                CONTROLLERS_WITHOUT_SITE_LIB=$((CONTROLLERS_WITHOUT_SITE_LIB + 1))
            else
                echo -e "  ${GREEN}✅ $controller_name: Site library properly attached${NC}"
                echo "SITE LIBRARY OK: $controller_name" >> "$REPORT_FILE"
            fi
        fi
    done
else
    echo -e "  ${RED}❌ Controllers directory not found${NC}"
    echo "ERROR: Controllers directory not found" >> "$REPORT_FILE"
fi

echo ""
echo -e "Controllers checked: ${BLUE}$(ls "$CONTROLLERS_DIR"/*.php 2>/dev/null | wc -l)${NC}"
echo -e "Controllers missing site library: ${YELLOW}$CONTROLLERS_WITHOUT_SITE_LIB${NC}"
echo "" >> "$REPORT_FILE"
echo "Controllers checked: $(ls "$CONTROLLERS_DIR"/*.php 2>/dev/null | wc -l)" >> "$REPORT_FILE"
echo "Controllers missing site library: $CONTROLLERS_WITHOUT_SITE_LIB" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Display final summary
echo -e "${BLUE}=== TEST SUMMARY ===${NC}"
echo -e "Total Pages Tested: ${BLUE}$TOTAL_PAGES${NC}"
echo -e "Pages Clean: ${GREEN}$PAGES_CLEAN${NC}"
echo -e "Pages with CSS Issues: ${YELLOW}$PAGES_WITH_CSS_ISSUES${NC}"
echo -e "Success Rate: ${BLUE}$(( (PAGES_CLEAN * 100) / TOTAL_PAGES ))%${NC}"
echo ""
echo -e "Full report saved to: ${BLUE}$REPORT_FILE${NC}"
echo -e "Page content saved to: ${BLUE}$RESULTS_DIR${NC}"

# Show pages with issues
if [ $PAGES_WITH_CSS_ISSUES -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}Pages with CSS Issues:${NC}"
    grep "CSS ISSUES:" "$REPORT_FILE" | sed 's/CSS ISSUES: /  - /'
fi

# Show any failed pages
failed_pages=$(grep -c "FAILED TO LOAD\|HTTP ERROR" "$REPORT_FILE" 2>/dev/null || echo "0")
if [ $failed_pages -gt 0 ]; then
    echo ""
    echo -e "${RED}Failed Pages:${NC}"
    grep "FAILED TO LOAD\|HTTP ERROR" "$REPORT_FILE" | sed 's/FAILED TO LOAD: /  - /' | sed 's/HTTP ERROR [0-9]*: /  - /'
fi

echo ""
echo -e "${BLUE}Testing complete!${NC}"