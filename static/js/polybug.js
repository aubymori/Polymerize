const YtdTopbarMenuButtonRenderer = customElements.get("ytd-topbar-menu-button-renderer");

class YtdErrorsButtonRenderer extends YtdTopbarMenuButtonRenderer
{
    static get properties()
    {
        return {
            data: Object
        };
    }

    get container()
    {
        return this.querySelector("div#button");
    }

    static get is()
    {
        return "ytd-errors-button-renderer";
    }
}

customElements.define(YtdErrorsButtonRenderer.is, YtdErrorsButtonRenderer);

// Modify the prototype of the ytd-masthead element's class to render our custom
// element with the topbar buttons.
const YtdMasthead = customElements.get("ytd-masthead");
YtdMasthead.prototype.stampDom =
{
    "data.interstitial": {
        "id": "interstitial",
        "mapping": {
            "consentBumpV2Renderer": "ytd-consent-bump-v2-renderer"
        }
    },
    "data.voiceSearchButton": {
        "id": "voice-search-button",
        "mapping": {
            "buttonRenderer": {
                "component": "ytd-button-renderer",
                "properties": {
                    "on-tap": "[[boundOnTapVoiceButton]]"
                }
            }
        }
    },
    "data.topbarButtons": {
        "id": "buttons",
        "events": true,
        "mapping": {
            "buttonRenderer": "ytd-button-renderer",
            "notificationTopbarButtonRenderer": "ytd-notification-topbar-button-renderer",
            "iconBadgeTopbarButtonRenderer": "ytd-icon-badge-topbar-button-renderer",
            "topbarMenuButtonRenderer": "ytd-topbar-menu-button-renderer",
            "errorsButtonRenderer": "ytd-errors-button-renderer" // Our custom element
        },
        "initialRenderPriority": 1
    },
    "data.a11ySkipNavigationButton": {
        "id": "skip-navigation",
        "mapping": {
            "buttonRenderer": "ytd-button-renderer"
        }   
    }
};