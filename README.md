# Tweet Loader (EE 2)

Retrieves Twitter content from a given account and saves the content as individual Channel Entries.

More specifically, Twitter Loader retrieves the latest Tweets for the given account, and checks which of them are missing from the DB and creates and entry for each if it is missing.

## Usage

* Download and extract the plugin files
* Copy `/system/expressionengine/third_party/tweet-loader` to your site's `/system/expressionengine/third_party/` directory
* Create the [Fields](#fields) and a corresponding Channel
* Fill in the [config](#config)
* Install the plugin
* Include `{exp:tweet_loader}` in a template file and load the template

## <a name="fields"></a>Fields

Tweet Loader uses the following fields, which should all be of type 'Text Input' and can be named as you wish:

* Tweet Text
* Id (of the Tweet post, not the EE entry)
* Tweet Link

## <a name="config"></a>Config

The config array is located in `/system/expressionengine/third_party/tweet-loader/tweet-loader.php`.

Each key needs a value (all are required) and should be a string.

### User Id

This is the id of the Twitter account for which you are wanting to retrieve content.

### User Handle

This is the handle of the Twitter account for which you are wanting to retrieve content.

### Channel Id

This is the id of your "Twitter" channel.

### Client Id, Consumer Key, Oauth, Oauth Token Secret

See [https://dev.twitter.com/](https://dev.twitter.com/) for App creation.

### Field Ids

The id corresponding to each field.