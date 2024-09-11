// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more util.details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * A module used for running code using the Coderunner webservice (CRWS) and displaying output. Originally
 * developed for use in the Scratchpad UI. It has three modes of operation:
 * - 'text': Just display the output as text, html escaped.
 * - 'json': The recommended way to display programs that use stdin or output images (or both).
 *      - Accepts JSON in the CRWS response output with fields:
 *          - "returncode": Error/return code from running program.
 *          - "stdout": Stdout text from running program.
 *          - "stderr": Error text from running program.
 *          - "files": An object containing filenames mapped to base64 encoded images.
 *                     These will be displayed below any stdout text.
 *      - When input from stdin is required the returncode 42 should be returned, raise this
 *        any time the program asks for input. An (html) input will be added after the last stdout received.
 *        When enter is pressed, runCode is called with value of the input added to the stdin string.
 *        This repeats until returncode is no longer 42.
 * - 'html': Display program output as raw html inside the output area.
 *      - This can be used to show images and insert other HTML tags (and beyond).
 *      - Giving an <input> tag the class 'coderunner-run-input' will add an event that
 *        on pressing enter will call the runCode method again with the value of that input field added to stdin.
 *        This method of receiving stdin is harder to use but more flexible than JSON, enter at your own risk.
 *
 * @module qtype_coderunner/outputdisplayarea
 * @copyright  James Napier, 2023, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ajax from 'core/ajax';
import {get_string as getLangString} from 'core/str';


const ENTER_KEY = 13;
const INPUT_INTERRUPT = 42;
const RESULT_SUCCESS = 15;
const INPUT_CLASS = 'coderunner-run-input';
const JSON_DISPLAY_PROPS = ['returncode', 'stdout', 'stderr', 'files'];


/**
 * Get the specified language string using
 * AJAX and plug it into the given textarea
 * @param {string} langStringName The language string name.
 * @param {DOMnode} node area into which the error message
 * should be plugged.
 * @param  {function} callback Callback function, with two arguments: node, message.
 * @param {string} additionalText Extra text to follow the result code.
 */
const setLangString = async(langStringName, node, callback, additionalText = '') => {
    let message = await getLangString(langStringName, 'qtype_coderunner');
    if (langStringName.includes('error')) {
        message = "*** " + message + " ***\n";
    }
    if (additionalText) {
        message += additionalText;
    }
    if (callback instanceof Function) {
        callback(node, message);
    } else {
        node.innerText = message;
    }
};

const diagnoseWebserviceResponse = (response) => {
    // Table of error conditions.
    // Each row is response.error, response.result, langstring
    // response.result is ignored if response.error is non-zero.
    // Any condition not in the table is deemed an "Unknown runtime error".
    const ERROR_RESPONSES = [
        [1, 0, 'error_access_denied'], // Sandbox AUTH_ERROR
        [2, 0, 'error_unknown_language'], // Sandbox WRONG_LANG_ID
        [3, 0, 'error_access_denied'], // Sandbox ACCESS_DENIED
        [4, 0, 'error_submission_limit_reached'], // Sandbox SUBMISSION_LIMIT_EXCEEDED
        [5, 0, 'error_sandbox_server_overload'], // Sandbox SERVER_OVERLOAD
        [0, 11, ''], // RESULT_COMPILATION_ERROR
        [0, 12, ''], // RESULT_RUNTIME_ERROR
        [0, 13, 'error_timeout'], // RESULT TIME_LIMIT
        [0, RESULT_SUCCESS, ''], // RESULT_SUCCESS
        [0, 17, 'error_memory_limit'], // RESULT_MEMORY_LIMIT
        [0, 21, 'error_sandbox_server_overload'], // RESULT_SERVER_OVERLOAD
        [0, 30, 'error_excessive_output'] // RESULT OUTPUT_LIMIT
    ];
    for (let i = 0; i < ERROR_RESPONSES.length; i++) {
        let row = ERROR_RESPONSES[i];
        if (row[0] == response.error && (response.error != 0 || response.result == row[1])) {
            return row[2];
        }
    }
    return 'error_unknown_runtime'; // We're dead, Fred.
};

/**
 * Concatenates the cmpinfo, stdout and stderr fields of the sandbox
 * response, truncating both stdout and stderr to a given maximum length
 * if necessary (in which case '... (truncated)' is appended.
 * @param {object} response Sandbox response object
 */
const combinedOutput = (response) => {
    return response.cmpinfo + response.output + response.stderr;
};

/**
 * Check whether obj has the properties in props, returns missing properties.
 * @param {object} obj to check properties of
 * @param {array} props to check for.
 * @returns {array} of missing properties.
 */
const missingProperties = (obj, props) => {
    return props.filter(prop => !obj.hasOwnProperty(prop));
};

/**
 * Insert a base64 encoded string into HTML image.
 * @param {string} base64 encoded string.
 * @param {string} type of encoded image file.
 * @returns {*|jQuery|HTMLElement} image tag containing encoded image from string.
 */
const getImage = (base64, type = 'png') => {
    const image = document.createElement('img');
    image.src = `data:image/${type};base64,${base64}`;
    return image;
};

/**
 * Constructor for OutputDisplayArea object. For use with the output_displayarea template.
 * @param {string} displayAreaId The id of the display area div, this should match the 'id'
 * from the template.
 * @param {string} outputMode The mode being used for output, must be text, html or json.
 * @param {string} lang The language to run code with.
 * @param {string} sandboxParams The sandbox params to run code with.
 */
class OutputDisplayArea {
    constructor(displayAreaId, outputMode, lang, sandboxParams) {
        this.displayAreaId = displayAreaId;
        this.lang = lang;
        this.mode = outputMode;
        this.sandboxParams = sandboxParams;

        this.textDisplay = document.getElementById(displayAreaId + '-text');
        this.imageDisplay = document.getElementById(displayAreaId + '-images');

        this.prevRunSettings = null;
    }

    /**
     * Clear the display of any images and text.
     */
    clearDisplay() {
        this.textDisplay.innerHTML = "";
        this.imageDisplay.innerHTML = "";
    }

    /**
     * Display text from a CRWS response to the display (escaped).
     * @param {object} response Coderunner webservice response JSON.
     */
    displayText(response) {
        this.textDisplay.innerText = combinedOutput(response);
    }

    /**
     * Display HTML from a CRWS response to the display (un-escaped).
     * Find the first HTML input element with the input class and
     * add event listeners to handle reading stdin.
     * @param {object} response Coderunner webservice response JSON,
     * with output field containing HTML.
     */
    displayHtml(response) {
        this.textDisplay.innerHTML = combinedOutput(response);
        const inputEl = this.textDisplay.querySelector('.' + INPUT_CLASS);
        if (inputEl) {
            this.addInputEvents(inputEl);
        }
    }

    /**
     * Display JSON from a CRWS response to the display.
     * Assumes response.output will be a JSON with the fields:
     *      - "returncode": Error/return code from running program.
     *      - "stdout": Stdout text from running program.
     *      - "stderr": Error text from running program.
     *      - "files": An object containing filenames mapped to base64 encoded images.
     *                 These will be displayed below any stdout text.
     * NOTE: See file header/readme for more info.
     * @param {object} response Coderunner webservice response JSON,
     * with output field containing JSON string.
     */
    displayJson(response) {
        const result = this.validateJson(response.output);

        let text = result.stdout;

        if (result.returncode !== INPUT_INTERRUPT) {
            text += result.stderr;
        }
        if (result.returncode == 13) { // Timeout
            setLangString('error_timeout', this.textDisplay, (node, msg) => {
             node.innerText += msg;
            });
        }

        const numImages = this.displayImages(result.files);
        if (text.trim() === '' && result.returncode !== INPUT_INTERRUPT) {
            if (numImages == 0) {
                this.displayNoOutput(null);
            }
        } else {
            this.textDisplay.innerText = text;
        }
        if (result.returncode === INPUT_INTERRUPT) {
            this.addInput();
        }
    }

    /**
     * Validate JSON to display, make sure it is valid json and has required fields.
     * @param {string} jsonString string of JSON to be displayed.
     * @returns {object} JSON as object
     */
    validateJson(jsonString) {
        let result = null;
        try {
            result = JSON.parse(jsonString);
        } catch (e) {
            window.alert(
                `Error parsing display JSON output: \n` +
                `'${jsonString}\n'` +
                `Error Msg: \n` +
                ` ${e.message} \n` +
                `The question author must fix this!`
            );
        }

        const missing = missingProperties(result, JSON_DISPLAY_PROPS);
        if (missing.length > 0) {
            window.alert(
                `Display JSON (in response.result) is missing the following fields: \n` +
                `${missing.join()} \n` +
                `The question author must fix this!`
            );
        }
        return result;
    }

    /**
     * Display no output message if no output to display or response is null.
     * @param {object} response Coderunner webservice response JSON, set to null to force
     * display of no output message.
     */
    displayNoOutput(response) {
        const isNoOutput = response ? combinedOutput(response).length === 0 : true;
        if (isNoOutput || response === null) {
            const span = document.createElement('span');
            span.style.color = 'red';
            setLangString('nooutput', span);
            this.clearDisplay();
            this.textDisplay.append(span);
        }
        return isNoOutput;
    }
    /**
     * Display response using the current display mode.
     * @param {object} response Coderunner webservice response JSON.
     */
    display(response) {
        const error = diagnoseWebserviceResponse(response);
        if (error !== '') {
            setLangString(error, this.textDisplay);
            return;
        }
        if (this.displayNoOutput(response)) {
            return;
        }

        if (this.mode === 'json') {
            this.displayJson(response);
        } else if (this.mode === 'html') {
            this.displayHtml(response);
        } else if (this.mode === 'text') {
            this.displayText(response);
        } else {
            throw Error(`Invalid outputMode given: "${this.mode}"`);
        }
    }

    /**
     * Run code using the Coderunner webservice and then display the output
     * using the selected mode. This function uses AJAX to asynchronously run and
     * display code.
     * @param {string} code to be run.
     * @param {string} stdin to be fed into the program.
     * @param {boolean} shouldClearDisplay will reset the display before displaying.
     * Use false when doing stdin runs.
     */
    runCode(code, stdin, shouldClearDisplay = false) {
        this.prevRunSettings = [code, stdin];
        if (shouldClearDisplay) {
            this.clearDisplay();
        }
        ajax.call([{
            methodname: 'qtype_coderunner_run_in_sandbox',
            args: {
                contextid: M.cfg.contextid, // Moodle context ID
                sourcecode: code,
                language: this.lang,
                stdin: stdin,
                params: JSON.stringify(this.sandboxParams) // Sandbox params
            },
            done: (responseJson) => {
                const response = JSON.parse(responseJson);
                this.display(response);
            },
            fail: (error) => {
                alert(error.message);
            }
        }]);
    }

    /**
     * Add an input field with event listeners to support running again
     * with new stdin entered by user.
     */
    addInput() {
        const inputId = `${this.displayAreaId}-input-field`;
        this.textDisplay.innerHTML += `<input type="text" id="${inputId}" class="${INPUT_CLASS}">`;
        const inputEl = document.getElementById(inputId);
        setLangString('enter_to_submit', inputEl, (node, msg) => {
            node.placeholder += msg;
        });
        this.addInputEvents(inputEl);
    }

    /**
     * Add event listeners to inputEl overriding enter key to:
     *  - Prevent form-submit.
     *  - Call runCode again, adding value in inputEl to stdin.
     * @param {node} inputEl to add event listeners to.
     */
    addInputEvents(inputEl) {
        inputEl.focus();

        inputEl.addEventListener('keydown', (e) => {
            if (e.keyCode === ENTER_KEY) {
                e.preventDefault(); // Do NOT form submit.
            }
        });
        inputEl.addEventListener('keyup', (e) => {
            if (e.keyCode === ENTER_KEY) {
                const line = inputEl.value;
                inputEl.remove();
                this.textDisplay.innterHTML += line; // Perhaps this should be sanitized.
                this.prevRunSettings[1] += line + '\n';
                this.runCode(...this.prevRunSettings, false);
            }
        });
    }

    /**
     * Take the files from a JSON response and display them.
     * @param {object} files from response, in filename: filecontents pairs.
     * @returns {number} number of images displayed.
     */
    displayImages(files) {
        let numImages = 0;
        for (const [fname, fcontents] of Object.entries(files)) {
            const fileType = fname.split('.')[1];
            if (fileType) {
                const image = getImage(fcontents, fileType);
                this.imageDisplay.append(image);
                numImages += 1;
            } else {
                window.alert(`Could not read filename correctly: "${fname}"`);
            }
        }
        return numImages;
    }
}


export {
    OutputDisplayArea
};
